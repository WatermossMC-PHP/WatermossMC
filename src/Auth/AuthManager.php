<?php
namespace Watermoss\Auth;

/**
 * AuthManager for MVP offline-mode.
 * If online-mode=false in server.properties, we skip complex Microsoft auth and create a fake profile.
 */
class AuthManager {
    private bool $onlineMode;

    public function __construct(bool $onlineMode = false) {
        $this->onlineMode = $onlineMode;
    }

    /**
     * For offline-mode this returns success with a generated xuid and profile.
     * For online-mode you would implement JWT/JWKS/XSTS flows.
     */
    public function verifyChainOffline(string $username): array {
        // create a fake xuid (string)
        $xuid = (string) (100000 + random_int(1, 999999));
        $profile = ['displayName' => $username, 'id' => md5($username)];
        return ['success' => true, 'xuid' => $xuid, 'profile' => $profile];
    }
}
