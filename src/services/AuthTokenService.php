<?php

require_once __DIR__ . '/JwtService.php';
require_once __DIR__ . '/../repositories/UserRefreshTokensRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class AuthTokenService
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return array{accessJwt: string, refreshToken: string, refreshExpires: int, sessionId: int}
     */
    public function issueTokens(int $userId, string $email, bool $rememberMe): array
    {
        $refreshToken = $this->generateRefreshToken();
        $tokenHash = $this->hashRefreshToken($refreshToken);
        $refreshTtl = $this->refreshTtlSeconds($rememberMe);
        $refreshExpires = time() + $refreshTtl;
        $expiresAt = (new DateTimeImmutable())->setTimestamp($refreshExpires);

        $sessionId = UserRefreshTokensRepository::getInstance()->create(
            $userId,
            $tokenHash,
            $expiresAt,
            $this->clientIp(),
            $this->clientUserAgent()
        );

        $accessJwt = $this->encodeAccessToken($userId, $email, $sessionId);

        return [
            'accessJwt' => $accessJwt,
            'refreshToken' => $refreshToken,
            'refreshExpires' => $refreshExpires,
            'sessionId' => $sessionId,
        ];
    }

    public function setAuthCookies(string $accessJwt, string $refreshToken, int $refreshExpires): void
    {
        $accessExpires = time() + $this->accessTtlSeconds();

        setcookie(
            $this->accessCookieName(),
            $accessJwt,
            $this->authCookieOptions($accessExpires)
        );
        setcookie(
            $this->refreshCookieName(),
            $refreshToken,
            $this->authCookieOptions($refreshExpires)
        );
    }

    public function clearAuthCookies(): void
    {
        $expired = $this->authCookieOptions(time() - 3600);
        setcookie($this->accessCookieName(), '', $expired);
        setcookie($this->refreshCookieName(), '', $expired);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveAuthenticatedPayload(): ?array
    {
        $accessToken = $_COOKIE[$this->accessCookieName()] ?? '';
        if ($accessToken !== '') {
            try {
                $payload = JwtService::decode($accessToken);
            } catch (RuntimeException) {
                $payload = null;
            }

            if (is_array($payload) && $this->isValidPayloadShape($payload)) {
                $sessionId = (int) ($payload['sid'] ?? 0);
                if ($sessionId > 0 && UserRefreshTokensRepository::getInstance()->findValidById($sessionId) !== null) {
                    return $payload;
                }
            }
        }

        $refreshed = $this->tryRefreshFromCookie();
        if ($refreshed !== null) {
            return $refreshed;
        }

        if ($accessToken !== '' || ($this->readRefreshTokenFromCookie() ?? '') !== '') {
            $this->clearAuthCookies();
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function tryRefreshFromCookie(): ?array
    {
        $refreshToken = $this->readRefreshTokenFromCookie();
        if ($refreshToken === null || $refreshToken === '') {
            return null;
        }

        $tokenHash = $this->hashRefreshToken($refreshToken);
        $repo = UserRefreshTokensRepository::getInstance();
        $session = $repo->findValidByTokenHash($tokenHash);
        if ($session === null) {
            return null;
        }

        $userId = (int) $session['user_id'];
        $user = UsersRepository::getInstance()->getUserById($userId);
        if ($user === null) {
            $repo->revoke((int) $session['id']);
            return null;
        }

        $email = (string) $user['email'];
        $rememberMe = $this->sessionLooksRemembered($session);
        $repo->revoke((int) $session['id']);

        $issued = $this->issueTokens($userId, $email, $rememberMe);
        $this->setAuthCookies(
            $issued['accessJwt'],
            $issued['refreshToken'],
            $issued['refreshExpires']
        );

        return $this->decodeAccessPayload($issued['accessJwt']);
    }

    public function revokeCurrentSession(): void
    {
        $sessionId = $this->resolveSessionIdForRevoke();
        if ($sessionId !== null && $sessionId > 0) {
            UserRefreshTokensRepository::getInstance()->revoke($sessionId);
        }

        $refreshToken = $this->readRefreshTokenFromCookie();
        if ($refreshToken !== null && $refreshToken !== '') {
            UserRefreshTokensRepository::getInstance()->revokeByTokenHash(
                $this->hashRefreshToken($refreshToken)
            );
        }

        $this->clearAuthCookies();
    }

    public function revokeAllSessionsForUser(int $userId): void
    {
        if ($userId > 0) {
            UserRefreshTokensRepository::getInstance()->revokeAllForUser($userId);
        }
        $this->clearAuthCookies();
    }

    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function encodeAccessToken(int $userId, string $email, int $sessionId): string
    {
        return JwtService::encode([
            'sub' => (string) $userId,
            'email' => $email,
            'sid' => $sessionId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeAccessPayload(string $accessJwt): ?array
    {
        try {
            $payload = JwtService::decode($accessJwt);
        } catch (RuntimeException) {
            return null;
        }

        if (!is_array($payload) || !$this->isValidPayloadShape($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isValidPayloadShape(array $payload): bool
    {
        return (int) ($payload['sub'] ?? 0) > 0 && (int) ($payload['sid'] ?? 0) > 0;
    }

    private function resolveSessionIdForRevoke(): ?int
    {
        $accessToken = $_COOKIE[$this->accessCookieName()] ?? '';
        if ($accessToken !== '') {
            try {
                $payload = JwtService::decode($accessToken);
            } catch (RuntimeException) {
                $payload = null;
            }

            if (is_array($payload)) {
                $sid = (int) ($payload['sid'] ?? 0);
                if ($sid > 0) {
                    return $sid;
                }
            }
        }

        $refreshToken = $this->readRefreshTokenFromCookie();
        if ($refreshToken === null || $refreshToken === '') {
            return null;
        }

        $session = UserRefreshTokensRepository::getInstance()->findValidByTokenHash(
            $this->hashRefreshToken($refreshToken)
        );

        return $session !== null ? (int) $session['id'] : null;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function sessionLooksRemembered(array $session): bool
    {
        $expiresAt = strtotime((string) ($session['expires_at'] ?? ''));
        $createdAt = strtotime((string) ($session['created_at'] ?? ''));

        if ($expiresAt === false || $createdAt === false) {
            return false;
        }

        $duration = $expiresAt - $createdAt;

        return $duration >= $this->refreshRememberTtlSeconds() - 3600;
    }

    private function readRefreshTokenFromCookie(): ?string
    {
        $token = $_COOKIE[$this->refreshCookieName()] ?? '';

        return $token !== '' ? $token : null;
    }

    private function accessCookieName(): string
    {
        return defined('JWT_COOKIE_NAME') ? JWT_COOKIE_NAME : 'auth_token';
    }

    private function refreshCookieName(): string
    {
        return defined('REFRESH_COOKIE_NAME') ? REFRESH_COOKIE_NAME : 'refresh_token';
    }

    private function accessTtlSeconds(): int
    {
        if (defined('JWT_ACCESS_TTL')) {
            return (int) JWT_ACCESS_TTL;
        }
        if (defined('JWT_TTL')) {
            return (int) JWT_TTL;
        }

        return 900;
    }

    private function refreshTtlSeconds(bool $rememberMe): int
    {
        if ($rememberMe) {
            return defined('JWT_REFRESH_REMEMBER_TTL') ? (int) JWT_REFRESH_REMEMBER_TTL : 2592000;
        }

        return defined('JWT_REFRESH_TTL') ? (int) JWT_REFRESH_TTL : 86400;
    }

    private function refreshRememberTtlSeconds(): int
    {
        return defined('JWT_REFRESH_REMEMBER_TTL') ? (int) JWT_REFRESH_REMEMBER_TTL : 2592000;
    }

    /**
     * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string}
     */
    private function authCookieOptions(int $expiresTimestamp): array
    {
        return [
            'expires' => $expiresTimestamp,
            'path' => '/',
            'secure' => $this->isConnectionSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function isConnectionSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }

    private function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    private function clientUserAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return is_string($ua) && $ua !== '' ? $ua : null;
    }
}
