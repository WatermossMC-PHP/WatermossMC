<?php
namespace Watermoss;

use Watermoss\Auth\AuthManager;
use Watermoss\Network\RakNet;
use Watermoss\Network\RakNetLayer;
use Watermoss\Protocol\BedrockPackets;
use Watermoss\Util\Logger;
use Watermoss\Util\Utils;
use Watermoss\World\FlatWorld;

class Server {
    private array $cfg;
    private $sock;
    private RakNetLayer $raknet;
    private AuthManager $auth;
    private array $sessions = [];

    public function __construct(string $propertiesPath) {
        $this->cfg = Utils::readProperties($propertiesPath);
        $online = strtolower($this->cfg['online-mode'] ?? 'false') === 'true';
        $this->auth = new AuthManager($online);
    }

    public function start(): void {
        $port = (int)($this->cfg['server-port'] ?? 19132);

        if (!function_exists('socket_create')) {
            Logger::error("php sockets extension required");
            exit(1);
        }

        $this->sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($this->sock === false) {
            Logger::error("socket_create failed");
            exit(1);
        }
        if (!socket_bind($this->sock, '0.0.0.0', $port)) {
            Logger::error("Failed to bind 0.0.0.0:$port");
            exit(1);
        }
        socket_set_nonblock($this->sock);

        // RakNet layer expects the socket; set property
        $this->raknet = new RakNetLayer($this->sock);

        Logger::info("WatermossMC listening on 0.0.0.0:$port (online-mode=" . ($this->cfg['online-mode'] ?? 'false') . ")");

        $lastTick = microtime(true);
        while (true) {
            $buf = '';
            $from = '';
            $fromPort = 0;
            $bytes = @socket_recvfrom($this->sock, $buf, 65535, 0, $from, $fromPort);

            if ($bytes !== false && $bytes > 0) {
                $this->handleDatagram($buf, $from, $fromPort);
            }

            // send any pending ACKs
            $acks = $this->raknet->popPendingAcks();
            foreach ($acks as $a) {
                @socket_sendto($this->sock, $a['payload'], strlen($a['payload']), 0, $a['to'], $a['port']);
            }

            // tick pacing ~20 TPS
            $now = microtime(true);
            $dt = $now - $lastTick;
            if ($dt < 0.05) usleep((int)((0.05 - $dt) * 1_000_000));
            $lastTick = microtime(true);
        }
    }

    private function handleDatagram(string $datagram, string $from, int $port): void {
        // If this datagram looks like raknet unconnected ping (first byte 0x01 and magic), reply pong
        if (strlen($datagram) > 1 && ord($datagram[0]) === ord(RakNet::UNCONNECTED_PING) && strpos($datagram, RakNet::MAGIC) !== false) {
            $this->handleUnconnectedPing($datagram, $from, $port);
            return;
        }

        // else parse via RakNetLayer -> frames -> messages
        $msgs = $this->raknet->parseDatagram($datagram, $from, $port);
        foreach ($msgs as $m) {
            $this->handleMessage($m['payload'], $from, $port, $m['reliable'] ?? false);
        }
    }

    private function handleUnconnectedPing(string $pkt, string $from, int $port): void {
        // Unconnected Ping format: 0x01 + time(8) + magic(16) + clientGUID(8)
        $time = substr($pkt, 1, 8);
        $motd = $this->buildMotd();
        $reply = RakNet::UNCONNECTED_PONG;
        $reply .= $time;
        $reply .= random_bytes(8); // server guid (not persistent in MVP)
        $reply .= RakNet::MAGIC;
        $reply .= pack('n', strlen($motd));
        $reply .= $motd;
        @socket_sendto($this->sock, $reply, strlen($reply), 0, $from, $port);
        Logger::info("Ping -> Pong to $from:$port");
    }

    private function buildMotd(): string {
        $m1 = $this->cfg['motd-line1'] ?? ($this->cfg['motd'] ?? 'WatermossMC');
        $m2 = $this->cfg['motd-line2'] ?? '';
        $proto = $this->cfg['protocol'] ?? '685';
        $ver = $this->cfg['version-name'] ?? '1.21.30';
        $max = $this->cfg['max-players'] ?? '10';
        $lvl = $this->cfg['level-name'] ?? 'world';
        $port = $this->cfg['server-port'] ?? '19132';
        return "MCPE;{$m1}\n{$m2};{$proto};{$ver};0;{$max};1;{$lvl};Survival;{$port};{$port}";
    }

    private function handleMessage(string $payload, string $from, int $port, bool $reliable): void {
        // Heuristic: we expect initial login to be a small JSON (MVP) or a plain login token.
        // For offline-mode, allow a simple "LOGIN:{username}" payload for ease of testing.
        if (str_starts_with($payload, 'LOGIN:')) {
            $username = substr($payload, 6);
            $res = $this->auth->verifyChainOffline($username);
            if (!$res['success']) {
                Logger::info("Auth failed for $from:$port");
                return;
            }
            $this->sessions["{$from}:{$port}"] = [
                'xuid' => $res['xuid'],
                'name' => $res['profile']['displayName'] ?? $username,
                'secret' => null
            ];
            Logger::info("Player {$this->sessions["{$from}:{$port}"]['name']} authenticated from $from:$port (offline-mode)");
            $this->finalizeLoginAndStart($from, $port, $this->sessions["{$from}:{$port}"]);
            return;
        }

        // Some test clients may send a JSON login; support both
        if (strlen($payload) > 0 && $payload[0] === '{') {
            $j = json_decode($payload, true);
            if (is_array($j) && isset($j['xuid']) || isset($j['name'])) {
                $username = $j['name'] ?? ('player' . random_int(1000,9999));
                $res = $this->auth->verifyChainOffline($username);
                $this->sessions["{$from}:{$port}"] = [
                    'xuid' => $res['xuid'],
                    'name' => $username,
                    'secret' => null
                ];
                Logger::info("Player $username authed via JSON (offline-mode) from $from:$port");
                $this->finalizeLoginAndStart($from, $port, $this->sessions["{$from}:{$port}"]);
                return;
            }
        }

        // ACK handler (skeleton)
        if (str_starts_with($payload, 'ACK')) {
            Logger::info("ACK from $from:$port");
            return;
        }

        Logger::info("Application payload from $from:$port (" . strlen($payload) . " bytes)");
    }

    private function finalizeLoginAndStart(string $from, int $port, array $session): void {
        // 1) PlayStatus - login success
        $ps = BedrockPackets::playStatusLoginSuccess();
        $this->raknet->send($from, $port, $ps, true);

        // 2) StartGame
        $sg = BedrockPackets::startGame($this->cfg);
        $this->raknet->send($from, $port, $sg, true);

        // 3) LevelChunk 0,0
        $chunk = FlatWorld::makeChunk(0,0);
        $lc = BedrockPackets::levelChunk(0, 0, $chunk);
        $this->raknet->send($from, $port, $lc, true);

        Logger::info("Started game for {$session['name']} at $from:$port (sent StartGame + chunk 0,0)");
    }
}
