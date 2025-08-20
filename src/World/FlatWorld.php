<?php
namespace Watermoss\World;

use Watermoss\Protocol\BedrockPackets;

class FlatWorld {
    /**
     * Create one minimal chunk for demo.
     * This is NOT a full correct Bedrock chunk with palettes/subchunks — it's a simple compressed payload many clients will accept as a placeholder.
     */
    public static function makeChunk(int $chunkX = 0, int $chunkZ = 0): string {
        $width = 16; $height = 16; $depth = 16;
        // simple block bytes
        $blocks = str_repeat("\x01", $width * $height * $depth); // placeholder block id
        $raw = pack('N', $chunkX) . pack('N', $chunkZ) . pack('N', $width) . pack('N', $height) . pack('N', $depth) . $blocks;
        return BedrockPackets::compress($raw);
    }
}
