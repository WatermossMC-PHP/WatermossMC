<?php
namespace Watermoss\Protocol;

/**
 * Minimal VarInt helpers (for typical Bedrock-ish encoding).
 * Note: Bedrock uses unsigned varints in many places; these helpers are small and used for demos.
 */
class VarInt {
    public static function writeVarInt(int $value): string {
        $out = '';
        while (true) {
            $temp = $value & 0x7F;
            $value >>= 7;
            if ($value !== 0) {
                $out .= chr($temp | 0x80);
            } else {
                $out .= chr($temp);
                break;
            }
        }
        return $out;
    }

    public static function readVarInt(string $data, int &$offset): int {
        $numRead = 0;
        $result = 0;
        $read = 0;
        do {
            $byte = ord($data[$offset++]);
            $value = $byte & 0x7F;
            $result |= ($value << (7 * $numRead));
            $numRead++;
            if ($numRead > 5) throw new \RuntimeException('VarInt too big');
        } while (($byte & 0x80) === 0x80);
        return $result;
    }
}
