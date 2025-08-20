<?php
namespace Watermoss\Protocol;

class BedrockPackets {
    /**
     * Minimal PlayStatus (Login Success)
     * This is a simplified wrapper: [packetId:1][code:4BE]
     * Many clients expect PlayStatus with code enum; code 2 = LOGIN_SUCCESS in some stacks.
     */
    public static function playStatusLoginSuccess(): string {
        $packetId = chr(0x01); // heuristic
        $code = pack('N', 2);
        return $packetId . $code;
    }

    /**
     * Minimal StartGame payload. This is VERY reduced — includes level name, seed, gamemode, max players.
     *
     * Real StartGame is complex (dimension codec, world settings, runtime entity ids, etc).
     */
    public static function startGame(array $cfg): string {
        $packetId = chr(0x05); // heuristic id
        $levelName = $cfg['level-name'] ?? 'world';
        $seed = pack('J', (int)($cfg['seed'] ?? 12345)); // platform dependent; used for demo
        $gamemode = chr((int)($cfg['gamemode'] ?? 0)); // survival
        $lenName = pack('n', strlen($levelName));
        $maxPlayers = pack('N', (int)($cfg['max-players'] ?? 10));
        // Combine into payload
        $payload = $packetId . $lenName . $levelName . $seed . $gamemode . $maxPlayers;
        return $payload;
    }

    /**
     * Minimal LevelChunk wrapper (packet id + coords + compressed data)
     */
    public static function levelChunk(int $cx, int $cz, string $compressedData): string {
        $packetId = chr(0x1d); // heuristic id
        $cxB = pack('N', $cx);
        $czB = pack('N', $cz);
        $len = pack('N', strlen($compressedData));
        return $packetId . $cxB . $czB . $len . $compressedData;
    }

    public static function compress(string $raw): string {
        return zlib_encode($raw, ZLIB_ENCODING_DEFLATE);
    }
}
