<?php

require_once 'Repository.php';

class FixedCostsRepository extends Repository
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
            INSERT INTO fixed_costs (user_id, icon, spending_name, spending_value, spending_currency, valid_from, valid_to)
            VALUES (:user_id, :icon, :spending_name, :spending_value, :spending_currency, CURRENT_TIMESTAMP, NULL)
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
     * Fixed costs active during the given calendar month.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveForMonth(int $userId, int $month, int $year): array
    {
        $monthStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $monthEnd = date('Y-m-d H:i:s', strtotime($monthStart . ' +1 month'));

        $query = $this->database->connect()->prepare(
            "
            SELECT id, icon, spending_name, spending_value, spending_currency, valid_from, valid_to
            FROM fixed_costs
            WHERE user_id = :user_id
              AND valid_from < :month_end
              AND (valid_to IS NULL OR valid_to >= :month_start)
            ORDER BY spending_name ASC
            "
        );
        $query->execute([
            ':user_id' => $userId,
            ':month_start' => $monthStart,
            ':month_end' => $monthEnd,
        ]);

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array<string, mixed>> $fixedCosts
     */
    public function sumValues(array $fixedCosts): float
    {
        $total = 0.0;
        foreach ($fixedCosts as $cost) {
            $total += (float) $cost['spending_value'];
        }

        return $total;
    }
}
