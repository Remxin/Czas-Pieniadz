<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/UserMetricsRepository.php';
require_once __DIR__ . '/../repositories/UserSpendingsRepository.php';
require_once __DIR__ . '/../repositories/FixedCostsRepository.php';
require_once __DIR__ . '/../services/LifeCostCalculator.php';
require_once __DIR__ . '/../config/IconCatalog.php';

class DashboardController extends AppController {

    private const ALLOWED_CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];

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

    public function index(?string $id = null) {
        if ($this->isPost()) {
            return $this->handleDashboardPost();
        }
        return $this->showDashboard();
    }

    private function showDashboard(): void
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

        $metrics = $metricsRepository->getLatestMetricsByType($userId);
        $currency = $user['default_currency'];
        $month = (int) date('n');
        $year = (int) date('Y');

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

        $recentSpendings = [];
        foreach ($spendingsRepository->getRecentByUser($userId) as $row) {
            $lifeHours = $calculator->lifeHoursForSpending((float) $row['spending_value'], $metrics);
            $recentSpendings[] = array_merge($row, [
                'life_hours' => $lifeHours,
                'life_hours_label' => $calculator->formatHoursShort($lifeHours),
                'theme_class' => IconCatalog::themeClass($row['icon']),
                'meta_label' => $this->formatRelativeDate($row['spending_date']),
            ]);
        }

        $displayName = trim((string) ($user['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = $user['username'];
        }

        $this->render('dashboard', [
            'userName' => $displayName,
            'currency' => $currency,
            'metrics' => $metrics,
            'metricsJson' => json_encode([
                'earnings' => (float) ($metrics['earnings'] ?? 0),
                'work_hours_per_month' => (float) ($metrics['work_hours_per_month'] ?? 0),
            ], JSON_THROW_ON_ERROR),
            'recentSpendings' => $recentSpendings,
            'monthlyTotal' => $monthlyTotal,
            'monthlySpendingsTotal' => $monthlySpendingsTotal,
            'monthlyFixedTotal' => $monthlyFixedTotal,
            'monthlyTotalFormatted' => $calculator->formatMoney($monthlyTotal, $currency),
            'monthlyFixedFormatted' => $calculator->formatMoney($monthlyFixedTotal, $currency),
            'hasFixedCosts' => $monthlyFixedTotal > 0,
            'monthlyHours' => $monthlyHours,
            'monthlyHoursFormatted' => $calculator->formatHours($monthlyHours),
            'monthlyHoursShort' => (int) round($monthlyHours),
            'workHoursLimit' => $workHoursLimit,
            'hoursOverLimit' => $hoursOverLimit,
            'hoursOverLimitFormatted' => $calculator->formatHours($hoursOverLimit),
            'showLimitAlert' => $hoursOverLimit > 0,
            'freedomLabel' => $freedom['label'],
            'freedomPercent' => $freedom['percent'],
            'monthLabel' => (self::MONTH_NAMES[$month] ?? '') . ' ' . $year,
            'monthLabelShort' => self::MONTH_NAMES[$month] ?? '',
            'icons' => IconCatalog::all(),
            'defaultIcon' => IconCatalog::DEFAULT_ICON,
            'error' => $_GET['error'] ?? null,
            'hasSpendings' => count($recentSpendings) > 0,
        ]);
    }

    private function handleDashboardPost(): void
    {
        $this->consumeJsonBody();
        $wantsJson = $this->wantsJson();
        $payload = $wantsJson ? $this->requireCompleteMetricsJson() : $this->requireCompleteMetrics();
        $userId = $this->userIdFromPayload($payload);

        $error = $this->validateDashboardInput();
        if ($error !== null) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'message' => $error], 422);
            }
            $this->redirectTo('/dashboard?error=' . rawurlencode($error));
        }

        $user = UsersRepository::getInstance()->getUserById($userId);
        if ($user === null) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'message' => 'Użytkownik nie istnieje.'], 401);
            }
            $this->redirectToLogin();
        }
        $currency = $user['default_currency'];
        $icon = IconCatalog::normalize(trim($_POST['icon'] ?? IconCatalog::DEFAULT_ICON));
        $name = trim($_POST['name'] ?? '');
        $price = (float) str_replace(',', '.', trim($_POST['price'] ?? '0'));
        $action = $_POST['action'] ?? '';
        $isFixedCost = $action === 'add_fixed_cost';

        if ($isFixedCost) {
            FixedCostsRepository::getInstance()->create($userId, $icon, $name, $price, $currency);
        } else {
            UserSpendingsRepository::getInstance()->create($userId, $icon, $name, $price, $currency);
        }

        if ($wantsJson) {
            $metricsRepository = UserMetricsRepository::getInstance();
            $metrics = $metricsRepository->getLatestMetricsByType($userId);
            $calculator = new LifeCostCalculator();

            $spendingPayload = null;
            if (!$isFixedCost) {
                $recent = UserSpendingsRepository::getInstance()->getRecentByUser($userId, 1);
                if ($recent !== []) {
                    $spendingPayload = $this->formatSpendingForJson($recent[0], $metrics, $calculator);
                }
            }

            $this->json([
                'ok' => true,
                'message' => $isFixedCost
                    ? 'Koszt stały został zapisany.'
                    : 'Wydatek został zapisany.',
                'isFixedCost' => $isFixedCost,
                'spending' => $spendingPayload,
                'summary' => $this->buildDashboardSummaryPayload($userId, $metrics, $calculator, $currency),
            ]);
        }

        $this->redirectTo('/dashboard?saved=1');
    }

    private function validateDashboardInput(): ?string
    {
        $action = $_POST['action'] ?? '';
        if (!in_array($action, ['add_spending', 'add_fixed_cost'], true)) {
            return 'Nieprawidłowa akcja.';
        }

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            return 'Nazwa wydatku jest wymagana.';
        }

        $priceRaw = trim($_POST['price'] ?? '');
        if ($priceRaw === '') {
            return 'Cena jest wymagana.';
        }

        $price = (float) str_replace(',', '.', $priceRaw);
        if ($price <= 0) {
            return 'Podaj prawidłową cenę.';
        }

        $icon = trim($_POST['icon'] ?? '');
        if ($icon !== '' && !IconCatalog::isAllowed($icon)) {
            return 'Nieprawidłowa ikona.';
        }

        return null;
    }

    private function formatRelativeDate(string $dateString): string
    {
        $timestamp = strtotime($dateString);
        if ($timestamp === false) {
            return '';
        }

        $diff = time() - $timestamp;
        if ($diff < 60) {
            return 'Przed chwilą';
        }
        if ($diff < 3600) {
            $mins = (int) floor($diff / 60);
            return $mins === 1 ? '1 minutę temu' : $mins . ' min temu';
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours === 1 ? '1 godzinę temu' : $hours . ' godz. temu';
        }
        if ($diff < 172800) {
            return 'Wczoraj, ' . date('H:i', $timestamp);
        }
        if ($diff < 604800) {
            $days = (int) floor($diff / 86400);
            return $days . ' dni temu';
        }

        return date('j M Y, H:i', $timestamp);
    }

    public function settings() {
        if ($this->isPost()) {
            return $this->saveSettings();
        }
        return $this->showSettings();
    }

    private function showSettings(): void
    {
        $payload = $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);

        $usersRepository = UsersRepository::getInstance();
        $metricsRepository = UserMetricsRepository::getInstance();

        $user = $usersRepository->getUserById($userId);
        if ($user === null) {
            $this->redirectToLogin();
        }

        $metrics = $metricsRepository->getLatestMetricsByType($userId);
        $metricsIncomplete = !$this->userHasCompleteMetrics($userId);

        $this->render('settings', [
            'metrics' => $metrics,
            'currency' => $user['default_currency'],
            'metricsIncomplete' => $metricsIncomplete,
            'error' => $_GET['error'] ?? null,
        ]);
    }

    private function saveSettings(): void
    {
        $this->consumeJsonBody();
        $wantsJson = $this->wantsJson();
        $payload = $wantsJson ? $this->requireAuthJson() : $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);

        $error = $this->validateSettingsInput();
        if ($error !== null) {
            if ($wantsJson) {
                $this->json(['ok' => false, 'message' => $error], 422);
            }

            $usersRepository = UsersRepository::getInstance();
            $user = $usersRepository->getUserById($userId);

            $this->render('settings', [
                'metrics' => $this->metricsFromPost(),
                'currency' => $_POST['currency'] ?? ($user['default_currency']),
                'metricsIncomplete' => !$this->userHasCompleteMetrics($userId),
                'error' => $error,
            ]);
            return;
        }

        $earnings = $this->parseIncome($_POST['income'] ?? '');
        $workDays = (float) ($_POST['days'] ?? 0);
        $workHours = (float) ($_POST['hours'] ?? 0);
        $currency = $_POST['currency'];

        $month = (int) date('n');
        $year = (int) date('Y');

        $metricsRepository = UserMetricsRepository::getInstance();
        $existing = $metricsRepository->getLatestMetricsByType($userId);
        $user = UsersRepository::getInstance()->getUserById($userId);

        $submitted = [
            'earnings' => $earnings,
            'work_days_per_week' => $workDays,
            'work_hours_per_month' => $workHours,
        ];

        $changed = false;
        foreach ($submitted as $metricName => $value) {
            if (!$this->metricValueChanged($existing[$metricName] ?? null, $value)) {
                continue;
            }
            $metricsRepository->upsertMetric($userId, $metricName, $value, $month, $year);
            $changed = true;
        }

        if ($user !== null && $currency !== $user['default_currency']) {
            UsersRepository::getInstance()->updateDefaultCurrency($userId, $currency);
            $changed = true;
        }

        if ($wantsJson) {
            $this->json([
                'ok' => true,
                'message' => 'Zapisano zmiany.',
                'changed' => $changed,
                'metricsIncomplete' => !$this->userHasCompleteMetrics($userId),
            ]);
        }

        $this->redirectTo('/settings' . ($changed ? '?saved=1' : ''));
    }

    private function metricValueChanged(?float $previous, float $new): bool
    {
        if ($previous === null) {
            return true;
        }

        return abs($previous - $new) > 0.001;
    }

    private function validateSettingsInput(): ?string
    {
        $incomeRaw = trim($_POST['income'] ?? '');
        if ($incomeRaw === '') {
            return 'Wynagrodzenie netto jest wymagane.';
        }

        $earnings = $this->parseIncome($incomeRaw);
        if ($earnings <= 0) {
            return 'Podaj prawidłowe wynagrodzenie netto.';
        }

        $daysRaw = trim($_POST['days'] ?? '');
        if ($daysRaw === '') {
            return 'Dni robocze w tygodniu są wymagane.';
        }

        $workDays = (float) $daysRaw;
        if ($workDays < 1 || $workDays > 7) {
            return 'Dni robocze w tygodniu muszą być od 1 do 7.';
        }

        $hoursRaw = trim($_POST['hours'] ?? '');
        if ($hoursRaw === '') {
            return 'Godziny miesięcznie są wymagane.';
        }

        $workHours = (float) $hoursRaw;
        if ($workHours <= 0 || $workHours > 744) {
            return 'Godziny miesięcznie muszą być większe od 0 i nie większe niż 744.';
        }

        $currency = $_POST['currency'] ?? '';
        if ($currency === '' || !in_array($currency, self::ALLOWED_CURRENCIES, true)) {
            return 'Wybierz prawidłową walutę.';
        }

        return null;
    }

    private function parseIncome(string $raw): float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($raw));
        return (float) $normalized;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, float> $metrics
     * @return array<string, mixed>
     */
    private function formatSpendingForJson(array $row, array $metrics, LifeCostCalculator $calculator): array
    {
        $value = (float) $row['spending_value'];
        $lifeHours = $calculator->lifeHoursForSpending($value, $metrics);

        return [
            'name' => $row['spending_name'],
            'icon' => $row['icon'],
            'theme_class' => IconCatalog::themeClass($row['icon']),
            'amount_formatted' => number_format($value, 2, ',', ' ') . ' ' . $row['spending_currency'],
            'life_hours_label' => $calculator->formatHoursShort($lifeHours),
            'meta_label' => $this->formatRelativeDate($row['spending_date']),
        ];
    }

    /**
     * @param array<string, float> $metrics
     * @return array<string, mixed>
     */
    private function buildDashboardSummaryPayload(
        int $userId,
        array $metrics,
        LifeCostCalculator $calculator,
        string $currency
    ): array {
        $month = (int) date('n');
        $year = (int) date('Y');

        $spendingsRepository = UserSpendingsRepository::getInstance();
        $fixedCostsRepository = FixedCostsRepository::getInstance();

        $monthly = $spendingsRepository->getMonthlySummary($userId, $month, $year);
        $activeFixedCosts = $fixedCostsRepository->getActiveForMonth($userId, $month, $year);
        $monthlyFixedTotal = $fixedCostsRepository->sumValues($activeFixedCosts);
        $monthlyTotal = $monthly['total_amount'] + $monthlyFixedTotal;

        $allForHours = $monthly['spendings'];
        foreach ($activeFixedCosts as $fixedCost) {
            $allForHours[] = ['spending_value' => $fixedCost['spending_value']];
        }
        $monthlyHours = $calculator->totalLifeHoursForSpendings($allForHours, $metrics);
        $workHoursLimit = (float) ($metrics['work_hours_per_month'] ?? 0);
        $hoursOverLimit = max(0, $monthlyHours - $workHoursLimit);
        $freedom = $calculator->freedomStatus($monthlyHours, $workHoursLimit);

        return [
            'monthlyTotalFormatted' => $calculator->formatMoney($monthlyTotal, $currency),
            'monthlyFixedFormatted' => $calculator->formatMoney($monthlyFixedTotal, $currency),
            'hasFixedCosts' => $monthlyFixedTotal > 0,
            'monthlyHoursFormatted' => $calculator->formatHours($monthlyHours),
            'monthlyHoursShort' => (int) round($monthlyHours),
            'showLimitAlert' => $hoursOverLimit > 0,
            'hoursOverLimitFormatted' => $calculator->formatHours($hoursOverLimit),
            'freedomLabel' => $freedom['label'],
            'freedomPercent' => $freedom['percent'],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function metricsFromPost(): array
    {
        return [
            'earnings' => $this->parseIncome($_POST['income'] ?? ''),
            'work_days_per_week' => (float) ($_POST['days'] ?? 0),
            'work_hours_per_month' => (float) ($_POST['hours'] ?? 0),
        ];
    }
}
