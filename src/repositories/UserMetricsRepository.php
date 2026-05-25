<?php

require_once 'Repository.php';

class UserMetricsRepository extends Repository
{
    public const REQUIRED_METRICS = [
        'earnings',
        'work_days_per_week',
        'work_hours_per_month',
    ];

    public function hasAllRequiredMetrics(int $userId): bool
    {
        $placeholders = implode(', ', array_fill(0, count(self::REQUIRED_METRICS), '?'));
        $params = array_merge([$userId], self::REQUIRED_METRICS);

        $query = $this->database->connect()->prepare(
            "
            SELECT COUNT(DISTINCT metric_name) AS cnt
            FROM user_metrics_history
            WHERE user_id = ?
              AND metric_name IN ({$placeholders})
            "
        );
        $query->execute($params);
        $row = $query->fetch(PDO::FETCH_ASSOC);

        return $row !== false && (int) $row['cnt'] === count(self::REQUIRED_METRICS);
    }

    /**
     * @return array<string, float> metric_name => metric_value
     */
    public function getLatestMetricsByType(int $userId): array
    {
        $query = $this->database->connect()->prepare(
            "
            SELECT DISTINCT ON (metric_name)
                metric_name,
                metric_value
            FROM user_metrics_history
            WHERE user_id = :user_id
            ORDER BY metric_name, metric_year DESC, metric_month DESC
            "
        );
        $query->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $query->execute();

        $metrics = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $metrics[$row['metric_name']] = (float) $row['metric_value'];
        }

        return $metrics;
    }

    public function upsertMetric(
        int $userId,
        string $metricName,
        float $value,
        int $month,
        int $year
    ): void {
        $query = $this->database->connect()->prepare(
            "
            INSERT INTO user_metrics_history (user_id, metric_name, metric_value, metric_month, metric_year)
            VALUES (:user_id, :metric_name, :metric_value, :metric_month, :metric_year)
            ON CONFLICT (user_id, metric_name, metric_month, metric_year)
            DO UPDATE SET metric_value = EXCLUDED.metric_value
            "
        );
        $query->execute([
            ':user_id' => $userId,
            ':metric_name' => $metricName,
            ':metric_value' => $value,
            ':metric_month' => $month,
            ':metric_year' => $year,
        ]);
    }
}
