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
     * Fixed costs that apply to the given calendar month.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveForMonth(int $userId, int $month, int $year): array
    {
        $monthStart = self::monthStart($month, $year);
        $monthEnd = self::monthEndExclusive($month, $year);

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
     * @return array<string, mixed>|null
     */
    public function getByIdForUser(int $id, int $userId): ?array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT id, valid_from, valid_to
            FROM fixed_costs
            WHERE id = :id AND user_id = :user_id
            "
        );
        $query->execute([':id' => $id, ':user_id' => $userId]);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Removes a fixed cost from history for a given view month.
     * Created in the view month: hard delete (gone everywhere).
     * Otherwise: valid_to = end of the previous month (still counts in earlier months).
     */
    public function removeForViewMonth(int $id, int $userId, int $viewMonth, int $viewYear): bool
    {
        $row = $this->getByIdForUser($id, $userId);
        if ($row === null) {
            return false;
        }

        $viewStartTs = strtotime(self::monthStart($viewMonth, $viewYear));
        $validFromTs = strtotime((string) $row['valid_from']);
        if ($validFromTs === false || $viewStartTs === false) {
            return false;
        }

        if ($validFromTs >= $viewStartTs) {
            return $this->deleteByIdForUser($id, $userId);
        }

        ['month' => $prevMonth, 'year' => $prevYear] = self::previousMonth($viewMonth, $viewYear);
        $validTo = self::monthEndInclusive($prevMonth, $prevYear);

        $query = $this->database->connect()->prepare(
            "
            UPDATE fixed_costs
            SET valid_to = :valid_to
            WHERE id = :id
              AND user_id = :user_id
              AND valid_to IS NULL
            "
        );
        $query->execute([
            ':valid_to' => $validTo,
            ':id' => $id,
            ':user_id' => $userId,
        ]);

        return $query->rowCount() > 0;
    }

    public function deleteByIdForUser(int $id, int $userId): bool
    {
        $query = $this->database->connect()->prepare(
            "
            DELETE FROM fixed_costs
            WHERE id = :id AND user_id = :user_id
            "
        );
        $query->execute([':id' => $id, ':user_id' => $userId]);

        return $query->rowCount() > 0;
    }

    /**
     * @return array{month: int, year: int}
     */
    public static function previousMonth(int $month, int $year): array
    {
        if ($month === 1) {
            return ['month' => 12, 'year' => $year - 1];
        }

        return ['month' => $month - 1, 'year' => $year];
    }

    public static function monthStart(int $month, int $year): string
    {
        return sprintf('%04d-%02d-01 00:00:00', $year, $month);
    }

    public static function monthEndExclusive(int $month, int $year): string
    {
        return date('Y-m-d H:i:s', strtotime(self::monthStart($month, $year) . ' +1 month'));
    }

    public static function monthEndInclusive(int $month, int $year): string
    {
        return date('Y-m-d H:i:s', strtotime(self::monthEndExclusive($month, $year) . ' -1 second'));
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
