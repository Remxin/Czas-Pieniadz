<?php

require_once 'Repository.php';

class UserSpendingsRepository extends Repository
{
    public function create(
        int $userId,
        string $icon,
        string $name,
        float $value,
        string $currency
    ): void {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO user_spendings (user_id, icon, spending_name, spending_value, spending_currency)
            VALUES (:user_id, :icon, :spending_name, :spending_value, :spending_currency)
            "
        );
        $query->execute([
            ':user_id' => $userId,
            ':icon' => $icon,
            ':spending_name' => $name,
            ':spending_value' => $value,
            ':spending_currency' => $currency,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentByUser(int $userId, int $limit = 5): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, icon, spending_name, spending_value, spending_currency, spending_date
            FROM user_spendings
            WHERE user_id = :user_id
            ORDER BY spending_date DESC
            LIMIT :limit
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->bindValue(':limit', $limit, PDO::PARAM_INT);
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{total_amount: float, spendings: array<int, array<string, mixed>>}
     */
    public function getMonthlySummary(int $userId, int $month, int $year): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, icon, spending_name, spending_value, spending_currency, spending_date
            FROM user_spendings
            WHERE user_id = :user_id
              AND EXTRACT(MONTH FROM spending_date) = :month
              AND EXTRACT(YEAR FROM spending_date) = :year
            ORDER BY spending_date DESC
            "
        );
        $query->execute([
            ':user_id' => $userId,
            ':month' => $month,
            ':year' => $year,
        ]);

        $spendings = $query->fetchAll(PDO::FETCH_ASSOC);
        $totalAmount = 0.0;
        foreach ($spendings as $spending) {
            $totalAmount += (float) $spending['spending_value'];
        }

        return [
            'total_amount' => $totalAmount,
            'spendings' => $spendings,
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getAvailableYears(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT DISTINCT EXTRACT(YEAR FROM spending_date)::int AS y
            FROM user_spendings
            WHERE user_id = :user_id
            ORDER BY y DESC
            "
        );
        $query->execute([':user_id' => $userId]);
        $years = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $years[] = (int) $row['y'];
        }

        $currentYear = (int) date('Y');
        if (!in_array($currentYear, $years, true)) {
            $years[] = $currentYear;
            rsort($years);
        }

        return $years !== [] ? $years : [$currentYear];
    }

    public function deleteByIdForUser(int $id, int $userId): bool
    {
        $query = $this->database->connect()->prepare(
            "
            DELETE FROM user_spendings
            WHERE id = :id AND user_id = :user_id
            "
        );
        $query->execute([':id' => $id, ':user_id' => $userId]);

        return $query->rowCount() > 0;
    }
}
