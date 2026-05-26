<?php

require_once 'Repository.php';

class UserRefreshTokensRepository extends Repository
{
    public function create(
        int $userId,
        string $tokenHash,
        DateTimeInterface $expiresAt,
        ?string $ip,
        ?string $userAgent
    ): int {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO user_refresh_tokens (user_id, token_hash, ip_address, user_agent, expires_at)
            VALUES (:user_id, :token_hash, :ip_address, :user_agent, :expires_at)
            RETURNING id
            "
        );
        $expires = $expiresAt->format('Y-m-d H:i:sP');
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':token_hash', $tokenHash);
        $query->bindValue(':ip_address', $ip);
        $query->bindValue(':user_agent', $userAgent);
        $query->bindValue(':expires_at', $expires);
        $query->execute();

        return (int) $query->fetchColumn();
    }

    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT *
            FROM user_refresh_tokens
            WHERE token_hash = :token_hash
              AND is_revoked = false
              AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
            "
        );
        $query->bindValue(':token_hash', $tokenHash);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function findValidById(int $sessionId): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT *
            FROM user_refresh_tokens
            WHERE id = :id
              AND is_revoked = false
              AND expires_at > CURRENT_TIMESTAMP
            LIMIT 1
            "
        );
        $query->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function revoke(int $sessionId): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE user_refresh_tokens
            SET is_revoked = true
            WHERE id = :id AND is_revoked = false
            "
        );
        $query->bindValue(':id', $sessionId, PDO::PARAM_INT);
        $query->execute();
    }

    public function revokeAllForUser(int $userId): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE user_refresh_tokens
            SET is_revoked = true
            WHERE user_id = :user_id AND is_revoked = false
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();
    }

    public function revokeByTokenHash(string $tokenHash): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE user_refresh_tokens
            SET is_revoked = true
            WHERE token_hash = :token_hash AND is_revoked = false
            "
        );
        $query->bindValue(':token_hash', $tokenHash);
        $query->execute();
    }
}
