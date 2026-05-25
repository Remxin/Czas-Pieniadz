<?php

require_once __DIR__ . '/../services/JwtService.php';
require_once __DIR__ . '/../repositories/UserMetricsRepository.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';

class AppController
{
    protected function isGet(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'GET';
    }

    protected function isPost(): bool
    {
        return $_SERVER["REQUEST_METHOD"] === 'POST';
    }

    protected function isConnectionSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    }

    /**
     * @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string}
     */
    protected function authCookieOptions(int $expiresTimestamp): array
    {
        return [
            'expires' => $expiresTimestamp,
            'path' => '/',
            'secure' => $this->isConnectionSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    protected function jwtCookieName(): string
    {
        return defined('JWT_COOKIE_NAME') ? JWT_COOKIE_NAME : 'auth_token';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function redirectTo(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }

    protected function clearAuthCookie(): void
    {
        setcookie(
            $this->jwtCookieName(),
            '',
            $this->authCookieOptions(time() - 3600)
        );
    }

    protected function redirectToLogin(): void
    {
        $this->clearAuthCookie();
        $this->redirectTo('/login');
    }

    protected function getJwtPayload(): ?array
    {
        $token = $_COOKIE[$this->jwtCookieName()] ?? '';
        if ($token === '') {
            return null;
        }
        try {
            $payload = JwtService::decode($token);
        } catch (RuntimeException) {
            return null;
        }

        if (!is_array($payload) || (int) ($payload['sub'] ?? 0) <= 0) {
            return null;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireAuth(): array
    {
        $payload = $this->getJwtPayload();
        if ($payload === null) {
            $this->redirectToLogin();
        }
        return $payload;
    }

    protected function userIdFromPayload(array $payload): int
    {
        return (int) ($payload['sub'] ?? 0);
    }

    protected function userHasCompleteMetrics(int $userId): bool
    {
        try {
            return UserMetricsRepository::getInstance()->hasAllRequiredMetrics($userId);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireCompleteMetrics(): array
    {
        $payload = $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);
        if (!$this->userHasCompleteMetrics($userId)) {
            $this->redirectTo('/settings');
        }
        return $payload;
    }

    protected function redirectAfterAuth(int $userId): void
    {
        if ($userId <= 0) {
            $this->redirectToLogin();
        }

        $user = UsersRepository::getInstance()->getUserById($userId);
        if ($user === null) {
            $this->redirectToLogin();
        }

        $path = $this->userHasCompleteMetrics($userId) ? '/dashboard' : '/settings';
        $this->redirectTo($path);
    }

    protected function render(string $template = null, array $variables = [])
    {
        $templatePath = 'public/views/'. $template.'.html';
        $templatePath404 = 'public/views/404.html';
        $output = "";

        if (file_exists($templatePath)) {
            extract($variables);

            ob_start();
            include $templatePath;
            $output = ob_get_clean();
        } else {
            ob_start();
            include $templatePath404;
            $output = ob_get_clean();
        }
        echo $output;
    }

}