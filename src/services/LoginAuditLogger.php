<?php

class LoginAuditLogger
{
    public static function logFailedAttempt(string $email, string $reason): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        error_log(sprintf(
            '[AUDIT] Failed login | email=%s | reason=%s | ip=%s | user_agent=%s',
            $email,
            $reason,
            $ip,
            $userAgent
        ));
    }
}
