<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/UserMetricsRepository.php';
require_once __DIR__ . '/../repositories/UserSpendingsRepository.php';
require_once __DIR__ . '/../repositories/FixedCostsRepository.php';
require_once __DIR__ . '/../services/LifeCostCalculator.php';
require_once __DIR__ . '/../config/IconCatalog.php';

class HistoryController extends AppController
{
    private const HISTORY_LIST_LIMIT = 8;

    private const MONTH_NAMES = [
        1 => 'Styczeń',
        2 => 'Luty',
        3 => 'Marzec',
        4 => 'Kwiecień',
        5 => 'Maj',
        6 => 'Czerwiec',
        7 => 'Lipiec',
        8 => 'Sierpień',
        9 => 'Wrzesień',
        10 => 'Październik',
        11 => 'Listopad',
        12 => 'Grudzień',
    ];

    public function index(?string $id = null)
    {
        if ($this->isPost()) {
            return $this->handleHistoryPost();
        }

        return $this->showHistory();
    }

    private function handleHistoryPost(): void
    {
        $payload = $this->requireCompleteMetrics();
        $userId = $this->userIdFromPayload($payload);

        ['month' => $month, 'year' => $year] = $this->resolveMonthYearFromPost();
        $redirectBase = '/history?month=' . $month . '&year=' . $year;

        $action = $_POST['action'] ?? '';
        $entryId = (int) ($_POST['entry_id'] ?? 0);
        if ($entryId <= 0) {
            $this->redirectTo($redirectBase . '&error=' . rawurlencode('Nieprawidłowy wpis.'));
        }

        if ($action === 'delete_spending') {
            $deleted = UserSpendingsRepository::getInstance()->deleteByIdForUser($entryId, $userId);
            if (!$deleted) {
                $this->redirectTo($redirectBase . '&error=' . rawurlencode('Nie znaleziono wydatku.'));
            }
            $this->redirectTo($redirectBase . '&deleted=1');
        }

        if ($action === 'delete_fixed_cost') {
            $fixedRepo = FixedCostsRepository::getInstance();
            $row = $fixedRepo->getByIdForUser($entryId, $userId);
            if ($row === null) {
                $this->redirectTo($redirectBase . '&error=' . rawurlencode('Nie znaleziono kosztu stałego.'));
            }
            if ($row['valid_to'] !== null) {
                $this->redirectTo($redirectBase . '&error=' . rawurlencode('Ten koszt stały jest już zamknięty.'));
            }
            $closed = $fixedRepo->removeForViewMonth($entryId, $userId, $month, $year);
            if (!$closed) {
                $this->redirectTo($redirectBase . '&error=' . rawurlencode('Nie udało się usunąć kosztu stałego.'));
            }
            $this->redirectTo($redirectBase . '&deleted=1');
        }

        $this->redirectTo($redirectBase . '&error=' . rawurlencode('Nieprawidłowa akcja.'));
    }

    private function showHistory(): void
    {
        $payload = $this->requireCompleteMetrics();
        $userId = $this->userIdFromPayload($payload);

        $usersRepository = UsersRepository::getInstance();
        $metricsRepository = UserMetricsRepository::getInstance();
        $spendingsRepository = UserSpendingsRepository::getInstance();
        $fixedCostsRepository = FixedCostsRepository::getInstance();
        $calculator = new LifeCostCalculator();

        $user = $usersRepository->getUserById($userId);
        if ($user === null) {
            $this->redirectToLogin();
        }

        $currency = $user['default_currency'];
        ['month' => $month, 'year' => $year] = $this->resolveMonthYearFromQuery();

        $metrics = $metricsRepository->getLatestMetricsByType($userId);
        $monthMetrics = $metricsRepository->getMetricsForMonth($userId, $month, $year);

        $monthly = $spendingsRepository->getMonthlySummary($userId, $month, $year);
        $activeFixedCosts = $fixedCostsRepository->getActiveForMonth($userId, $month, $year);
        $monthlyFixedTotal = $fixedCostsRepository->sumValues($activeFixedCosts);
        $monthlySpendingsTotal = $monthly['total_amount'];
        $monthlyTotal = $monthlySpendingsTotal + $monthlyFixedTotal;

        $allForHours = $monthly['spendings'];
        foreach ($activeFixedCosts as $fixedCost) {
            $allForHours[] = ['spending_value' => $fixedCost['spending_value']];
        }
        $monthlyHours = $calculator->totalLifeHoursForSpendings($allForHours, $metrics);
        $workHoursLimit = (float) ($metrics['work_hours_per_month'] ?? 0);
        $hoursOverLimit = max(0, $monthlyHours - $workHoursLimit);
        $freedom = $calculator->freedomStatus($monthlyHours, $workHoursLimit);

        $fixedCount = count($activeFixedCosts);
        $spendingCount = count($monthly['spendings']);
        $entryCount = $fixedCount + $spendingCount;
        $historyRows = $this->buildHistoryRows($activeFixedCosts, $monthly['spendings'], $metrics, $calculator, $currency);
        $entryCountLabel = $this->formatEntryCountLabel(count($historyRows), $entryCount);

        $hourlyRate = $calculator->hourlyRate($monthMetrics);
        $hourlyRateFormatted = $calculator->formatHourlyRate($hourlyRate, $currency);
        $prevMonthYear = $this->previousMonthYear($month, $year);
        $prevMonthMetrics = $metricsRepository->getMetricsForMonth(
            $userId,
            $prevMonthYear['month'],
            $prevMonthYear['year']
        );
        $prevHourlyRate = $calculator->hourlyRate($prevMonthMetrics);
        $hourlyRateNote = $this->formatHourlyRateNote($hourlyRate, $prevHourlyRate, $currency, $prevMonthYear);

        $workMonthPercent = $workHoursLimit > 0
            ? (int) min(100, round(($monthlyHours / $workHoursLimit) * 100))
            : 0;

        $yearOptions = $spendingsRepository->getAvailableYears($userId);
        if (!in_array($year, $yearOptions, true)) {
            $yearOptions[] = $year;
            rsort($yearOptions);
        }

        $this->render('history', [
            'currency' => $currency,
            'historyRows' => $historyRows,
            'hasRows' => $entryCount > 0,
            'entryCount' => $entryCount,
            'entryCountLabel' => $entryCountLabel,
            'monthlyTotalFormatted' => $calculator->formatMoney($monthlyTotal, $currency),
            'monthlyFixedFormatted' => $calculator->formatMoney($monthlyFixedTotal, $currency),
            'hasFixedCosts' => $monthlyFixedTotal > 0,
            'monthlyHoursFormatted' => $calculator->formatHours($monthlyHours),
            'workMonthPercent' => $workMonthPercent,
            'hourlyRateFormatted' => $hourlyRateFormatted,
            'hourlyRateNote' => $hourlyRateNote,
            'freedomLabel' => $freedom['label'],
            'freedomPercent' => $freedom['percent'],
            'showLimitAlert' => $hoursOverLimit > 0,
            'hoursOverLimitFormatted' => $calculator->formatHours($hoursOverLimit),
            'monthLabel' => (self::MONTH_NAMES[$month] ?? '') . ' ' . $year,
            'monthLabelShort' => self::MONTH_NAMES[$month] ?? '',
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'monthOptions' => self::MONTH_NAMES,
            'yearOptions' => $yearOptions,
            'deleted' => isset($_GET['deleted']),
            'error' => $_GET['error'] ?? null,
        ]);
    }

    /**
     * @return array{month: int, year: int}
     */
    private function resolveMonthYearFromQuery(): array
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        $month = isset($_GET['month']) ? (int) $_GET['month'] : $currentMonth;
        $year = isset($_GET['year']) ? (int) $_GET['year'] : $currentYear;

        if ($month < 1 || $month > 12) {
            $month = $currentMonth;
        }
        if ($year < 2001 || $year > $currentYear + 1) {
            $year = $currentYear;
        }

        return ['month' => $month, 'year' => $year];
    }

    /**
     * @return array{month: int, year: int}
     */
    private function resolveMonthYearFromPost(): array
    {
        $fromQuery = $this->resolveMonthYearFromQuery();
        $month = isset($_POST['month']) ? (int) $_POST['month'] : $fromQuery['month'];
        $year = isset($_POST['year']) ? (int) $_POST['year'] : $fromQuery['year'];

        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');
        if ($month < 1 || $month > 12) {
            $month = $fromQuery['month'];
        }
        if ($year < 2001 || $year > $currentYear + 1) {
            $year = $fromQuery['year'];
        }

        return ['month' => $month, 'year' => $year];
    }

    /**
     * @param array<int, array<string, mixed>> $fixedCosts
     * @param array<int, array<string, mixed>> $spendings
     * @param array<string, float> $metrics
     * @return array<int, array<string, mixed>>
     */
    private function buildHistoryRows(
        array $fixedCosts,
        array $spendings,
        array $metrics,
        LifeCostCalculator $calculator,
        string $currency
    ): array {
        $rows = [];

        foreach ($fixedCosts as $row) {
            $value = (float) $row['spending_value'];
            $lifeHours = $calculator->lifeHoursForSpending($value, $metrics);
            $rows[] = [
                'id' => (int) $row['id'],
                'entry_type' => 'fixed',
                'icon' => $row['icon'],
                'table_theme_class' => IconCatalog::tableThemeClass($row['icon']),
                'spending_name' => $row['spending_name'],
                'amount_formatted' => number_format($value, 2, ',', ' ') . ' ' . $row['spending_currency'],
                'life_hours_label' => $calculator->formatHoursShort($lifeHours),
                'date_label' => 'Koszty stałe',
                'is_fixed' => true,
            ];
        }

        foreach ($spendings as $row) {
            $value = (float) $row['spending_value'];
            $lifeHours = $calculator->lifeHoursForSpending($value, $metrics);
            $rows[] = [
                'id' => (int) $row['id'],
                'entry_type' => 'spending',
                'icon' => $row['icon'],
                'table_theme_class' => IconCatalog::tableThemeClass($row['icon']),
                'spending_name' => $row['spending_name'],
                'amount_formatted' => number_format($value, 2, ',', ' ') . ' ' . $row['spending_currency'],
                'life_hours_label' => $calculator->formatHoursShort($lifeHours),
                'date_label' => $this->formatSpendingDate($row['spending_date']),
                'is_fixed' => false,
            ];
        }

        return array_slice($rows, 0, self::HISTORY_LIST_LIMIT);
    }

    private function formatSpendingDate(string $dateString): string
    {
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return '';
        }

        $months = [
            1 => 'sty', 2 => 'lut', 3 => 'mar', 4 => 'kwi', 5 => 'maj', 6 => 'cze',
            7 => 'lip', 8 => 'sie', 9 => 'wrz', 10 => 'paź', 11 => 'lis', 12 => 'gru',
        ];
        $month = (int) date('n', $timestamp);

        return date('j', $timestamp) . ' ' . ($months[$month] ?? date('M', $timestamp)) . ' ' . date('Y', $timestamp);
    }

    private function formatEntryCountLabel(int $visibleCount, int $entryCount): ?string
    {
        if ($entryCount === 0) {
            return null;
        }
        if ($entryCount <= self::HISTORY_LIST_LIMIT) {
            return $entryCount === 1
                ? 'Wyświetlanie 1 wpisu'
                : 'Wyświetlanie 1–' . $entryCount . ' z ' . $entryCount . ' wpisów';
        }

        return 'Wyświetlanie 1–' . $visibleCount . ' z ' . $entryCount . ' wpisów';
    }

    /**
     * @return array{month: int, year: int}
     */
    private function previousMonthYear(int $month, int $year): array
    {
        if ($month === 1) {
            return ['month' => 12, 'year' => $year - 1];
        }

        return ['month' => $month - 1, 'year' => $year];
    }

    /**
     * @param array{month: int, year: int} $prevMonthYear
     */
    private function formatHourlyRateNote(
        float $currentRate,
        float $prevRate,
        string $currency,
        array $prevMonthYear
    ): ?string {
        if ($currentRate <= 0 || $prevRate <= 0) {
            return null;
        }

        $prevLabel = self::MONTH_NAMES[$prevMonthYear['month']] ?? '';
        $prevFormatted = number_format($prevRate, 2, ',', ' ') . ' ' . $currency . '/h';

        if (abs($currentRate - $prevRate) < 0.01) {
            return 'Bez zmian względem ' . $prevLabel;
        }

        if ($currentRate > $prevRate) {
            return 'Wzrost względem ' . $prevLabel . ' (' . $prevFormatted . ')';
        }

        return 'Spadek względem ' . $prevLabel . ' (' . $prevFormatted . ')';
    }
}
