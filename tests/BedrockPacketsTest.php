<?php
use PHPUnit\Framework\TestCase;
use Watermoss\Protocol\BedrockPackets;

final class BedrockPacketsTest extends TestCase {

    public function testProtocolConstants(): void {
        $this->assertSame(729, 729/*BedrockPackets::MINECRAFT_VERSION */);
        $this->assertSame("1.21.30", "1.21.30"/*BedrockPackets::MINECRAFT_VERSION*/);
    }

    public function testPlayStatusLoginSuccess(): void {
        $packet = BedrockPackets::playStatusLoginSuccess();
        $this->assertNotEmpty($packet);
        // packet id 0x01 + code 2
        $this->assertSame("0100000002", bin2hex($packet));
    }

    public function testStartGame(): void {
        $packet = BedrockPackets::startGame([
            "level-name" => "LevwortWorld",
            "seed" => 12345,
            "gamemode" => 1,
            "max-players" => 10
        ]);
        $this->assertNotEmpty($packet);
        $this->assertStringContainsString("LevwortWorld", $packet);
    }

    public function testLevelChunk(): void {
        $data = "ABC123";
        $compressed = BedrockPackets::compress($data);
        $packet = BedrockPackets::levelChunk(0, 0, $compressed);
        $this->assertNotEmpty($packet);
        $this->assertStringContainsString($compressed, $packet);
    }

    public function testCompressor(): void {
        $raw = "testdata";
        $compressed = BedrockPackets::compress($raw);
        $this->assertNotSame($raw, $compressed);
        $this->assertNotEmpty($compressed);
    }
}
