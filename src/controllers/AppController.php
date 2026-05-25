<?php

require_once __DIR__ . '/../services/JwtService.php';
require_once __DIR__ . '/../repositories/UserMetricsRepository.php';

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
    protected function getJwtPayload(): ?array
    {
        $token = $_COOKIE[$this->jwtCookieName()] ?? '';
        if ($token === '') {
            return null;
        }
        try {
            return JwtService::decode($token);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireAuth(): array
    {
        $payload = $this->getJwtPayload();
        if ($payload === null) {
            $url = "http://{$_SERVER['HTTP_HOST']}/login";
            header("Location: {$url}");
            exit;
        }
        return $payload;
    }

    protected function userIdFromPayload(array $payload): int
    {
        return (int) ($payload['sub'] ?? 0);
    }

    protected function userHasCompleteMetrics(int $userId): bool
    {
        return UserMetricsRepository::getInstance()->hasAllRequiredMetrics($userId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireCompleteMetrics(): array
    {
        $payload = $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);
        if (!$this->userHasCompleteMetrics($userId)) {
            $url = "http://{$_SERVER['HTTP_HOST']}/settings";
            header("Location: {$url}");
            exit;
        }
        return $payload;
    }

    protected function redirectAfterAuth(int $userId): void
    {
        $path = $this->userHasCompleteMetrics($userId) ? '/dashboard' : '/settings';
        $url = "http://{$_SERVER['HTTP_HOST']}{$path}";
        header("Location: {$url}");
        exit;
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