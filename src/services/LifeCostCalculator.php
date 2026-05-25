<?php

class LifeCostCalculator
{
    private const WORK_DAY_HOURS = 8.0;

    /**
     * @param array{earnings: float, work_hours_per_month: float} $metrics
     * @return array{life_hours: float, work_days: float, income_percent: float}
     */
    public function calculate(float $price, array $metrics): array
    {
        $lifeHours = $this->lifeHoursForSpending($price, $metrics);

        return [
            'life_hours' => $lifeHours,
            'work_days' => $lifeHours / self::WORK_DAY_HOURS,
            'income_percent' => ($price / $metrics['earnings']) * 100,
        ];
    }

    /**
     * @param array{earnings: float, work_hours_per_month: float} $metrics
     */
    public function lifeHoursForSpending(float $price, array $metrics): float
    {
        if ($metrics['earnings'] <= 0 || $metrics['work_hours_per_month'] <= 0) {
            return 0.0;
        }

        return $price * $metrics['work_hours_per_month'] / $metrics['earnings'];
    }

    /**
     * @param array<int, array{spending_value: float|string}> $spendings
     * @param array{earnings: float, work_hours_per_month: float} $metrics
     */
    public function totalLifeHoursForSpendings(array $spendings, array $metrics): float
    {
        $total = 0.0;
        foreach ($spendings as $spending) {
            $total += $this->lifeHoursForSpending((float) $spending['spending_value'], $metrics);
        }

        return $total;
    }

    public function hoursBarPercent(float $lifeHours, float $workHoursPerMonth): int
    {
        if ($workHoursPerMonth <= 0) {
            return 0;
        }

        return (int) min(100, round(($lifeHours / $workHoursPerMonth) * 100));
    }

    public function daysBarPercent(float $workDays, float $workHoursPerMonth): int
    {
        $maxDays = $workHoursPerMonth / self::WORK_DAY_HOURS;
        if ($maxDays <= 0) {
            return 0;
        }

        return (int) min(100, round(($workDays / $maxDays) * 100));
    }

    public function incomeBarPercent(float $incomePercent): int
    {
        return (int) min(100, round($incomePercent));
    }

    public function formatHours(float $hours): string
    {
        return number_format($hours, 1, ',', ' ');
    }

    public function formatDays(float $days): string
    {
        return number_format($days, 1, ',', ' ');
    }

    public function formatPercent(float $percent): string
    {
        return (string) (int) round($percent) . '%';
    }

    public function formatMoney(float $amount, string $currency): string
    {
        return number_format($amount, 2, ',', ' ') . ' ' . $currency;
    }

    public function formatHoursShort(float $hours): string
    {
        return (string) (int) round($hours) . 'h';
    }

    /**
     * @return array{label: string, percent: int}
     */
    public function freedomStatus(float $monthlyHours, float $workHoursPerMonth): array
    {
        if ($workHoursPerMonth <= 0) {
            return ['label' => '—', 'percent' => 0];
        }

        $ratio = $monthlyHours / $workHoursPerMonth;
        $percent = (int) min(100, round($ratio * 100));

        if ($ratio <= 0.8) {
            return ['label' => 'Dobry', 'percent' => $percent];
        }
        if ($ratio <= 1.0) {
            return ['label' => 'Umiarkowany', 'percent' => $percent];
        }

        return ['label' => 'Krytyczny', 'percent' => min(100, $percent)];
    }
}
