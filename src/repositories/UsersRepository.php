<?php

require_once 'Repository.php';

class UsersRepository extends Repository {

    public function getUsers(): ?array 
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users;
            "
        );
        $query->execute();

        $users = $query->fetchAll(PDO::FETCH_ASSOC);
        return $users;
    }

    public function getUserByEmail(string $email) {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users WHERE email = :email
            "
        );
        $query->bindParam(':email', $email);
        $query->execute();

        $user = $query->fetch(PDO::FETCH_ASSOC);
        return $user;
    }

    public function getUserById(int $id): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT * FROM users WHERE id = :id
            "
        );
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute();

        $user = $query->fetch(PDO::FETCH_ASSOC);
        return $user !== false ? $user : null;
    }

    public function updateDefaultCurrency(int $userId, string $currency): void
    {
        $query = $this->database->connect()->prepare(
            "
            UPDATE users SET default_currency = :currency, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
            "
        );
        $query->execute([
            ':currency' => $currency,
            ':id' => $userId,
        ]);
    }

    public function createUser(
        string $email,
        string $hashedPassword,
        string $username,
    ) {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO users (username, email, password)
            VALUES (?, ?, ?);
            "
        );
        $query->execute([
            $username,
            $email, 
            $hashedPassword
        ]);
    }
}