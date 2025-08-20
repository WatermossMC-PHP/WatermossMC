<?php
namespace Watermoss\Network;

use Watermoss\Util\Logger;

/**
 * Simplified RakNetLayer:
 * - build/parse single frames
 * - reliable flag + simple seq
 * - fragmentation + reassembly
 * - pending ACK buffer (JSON skeleton)
 *
 * Not full RakNet spec; good enough for MVP usage.
 */
class RakNetLayer {
    private $socket;
    private int $outSeq = 1;
    private array $fragments = [];
    private array $pendingAcks = [];
    private array $receivedSeqs = [];

    public function __construct($socket) {
        $this->socket = $socket;
    }

    public function parseDatagram(string $datagram, string $from, int $port): array {
        // For MVP assume datagram = one frame
        return $this->parseFrame($datagram, $from, $port);
    }

    private function parseFrame(string $frame, string $from, int $port): array {
        $len = strlen($frame);
        if ($len === 0) return [];

        $ptr = 0;
        $flags = ord($frame[$ptr++]);
        $isRel = (bool)($flags & 0x01);
        $isFrag = (bool)($flags & 0x02);

        $seq = null;
        if ($isRel) {
            if ($ptr + 4 > $len) return [];
            $seq = unpack('N', substr($frame, $ptr, 4))[1];
            $ptr += 4;

            $src = "{$from}:{$port}";
            $this->receivedSeqs[$src] ??= [];
            if (in_array($seq, $this->receivedSeqs[$src], true)) {
                return []; // duplicate
            }
            $this->receivedSeqs[$src][] = $seq;
            if (count($this->receivedSeqs[$src]) > 1024) array_shift($this->receivedSeqs[$src]);

            $this->pendingAcks[$src] ??= [];
            $this->pendingAcks[$src][] = $seq;
        }

        if ($isFrag) {
            if ($ptr + 8 + 2 + 2 > $len) return [];
            $msgId = substr($frame, $ptr, 8); $ptr += 8;
            $index = unpack('n', substr($frame, $ptr, 2))[1]; $ptr += 2;
            $count = unpack('n', substr($frame, $ptr, 2))[1]; $ptr += 2;
            $dlen = unpack('n', substr($frame, $ptr, 2))[1]; $ptr += 2;
            if ($ptr + $dlen > $len) return [];
            $data = substr($frame, $ptr, $dlen);

            $key = "{$from}:{$port}:" . bin2hex($msgId);
            $this->fragments[$key]['parts'][$index] = $data;
            $this->fragments[$key]['count'] = $count;

            if (count($this->fragments[$key]['parts']) === $count) {
                ksort($this->fragments[$key]['parts']);
                $payload = implode('', $this->fragments[$key]['parts']);
                unset($this->fragments[$key]);
                return [['payload'=>$payload,'from'=>$from,'port'=>$port,'reliable'=>$isRel,'seq'=>$seq]];
            } else {
                return [];
            }
        }

        $payload = substr($frame, $ptr);
        return [['payload'=>$payload,'from'=>$from,'port'=>$port,'reliable'=>$isRel,'seq'=>$seq]];
    }

    public function buildFrames(string $payload, bool $reliable=false, int $mtu=1200): array {
        $frames = [];
        $seq = null;
        if ($reliable) {
            $seq = $this->outSeq++;
            if ($this->outSeq > 0x7fffffff) $this->outSeq = 1;
        }

        $header = 0;
        if ($reliable) $header |= 0x01;

        if (strlen($payload) + 8 <= $mtu) {
            $frame = chr($header);
            if ($reliable) $frame .= pack('N', $seq);
            $frame .= $payload;
            $frames[] = $frame;
            return $frames;
        }

        // fragmentation
        $header |= 0x02;
        $msgId = random_bytes(8);
        $chunksize = $mtu - 64;
        $parts = str_split($payload, $chunksize);
        $count = count($parts);
        foreach ($parts as $i => $chunk) {
            $frame = chr($header);
            if ($reliable) $frame .= pack('N', $seq);
            $frame .= $msgId;
            $frame .= pack('n', $i);
            $frame .= pack('n', $count);
            $frame .= pack('n', strlen($chunk));
            $frame .= $chunk;
            $frames[] = $frame;
        }
        return $frames;
    }

    public function send(string $toIp, int $toPort, string $payload, bool $reliable=false): void {
        $frames = $this->buildFrames($payload, $reliable);
        foreach ($frames as $f) {
            @socket_sendto($this->socket, $f, strlen($f), 0, $toIp, $toPort);
        }
    }

    public function popPendingAcks(): array {
        $out = [];
        foreach ($this->pendingAcks as $dst => $seqs) {
            if (empty($seqs)) continue;
            [$ip,$port] = explode(':',$dst);
            $payload = "ACK" . json_encode($seqs);
            $out[] = ['to'=>$ip,'port'=>(int)$port,'payload'=>$payload];
            $this->pendingAcks[$dst] = [];
        }
        return $out;
    }
}
