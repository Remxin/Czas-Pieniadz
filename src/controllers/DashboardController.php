<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../repositories/UserMetricsRepository.php';

class DashboardController extends AppController {

    private const ALLOWED_CURRENCIES = ['PLN', 'EUR', 'USD', 'GBP'];

    public function index(?string $id = null) {
        $this->requireCompleteMetrics();
        return $this->render('dashboard');
    }

    public function settings() {
        if ($this->isPost()) {
            return $this->saveSettings();
        }
        return $this->showSettings();
    }

    public function history() {
        $this->requireCompleteMetrics();
        return $this->render('history');
    }

    private function showSettings(): void
    {
        $payload = $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);

        $usersRepository = UsersRepository::getInstance();
        $metricsRepository = UserMetricsRepository::getInstance();

        $user = $usersRepository->getUserById($userId);
        if ($user === null) {
            $url = "http://{$_SERVER['HTTP_HOST']}/login";
            header("Location: {$url}");
            exit;
        }

        $metrics = $metricsRepository->getLatestMetricsByType($userId);
        $metricsIncomplete = !$this->userHasCompleteMetrics($userId);

        $this->render('settings', [
            'metrics' => $metrics,
            'currency' => $user['default_currency'],
            'metricsIncomplete' => $metricsIncomplete,
            'error' => $_GET['error'] ?? null,
            'saved' => isset($_GET['saved']),
        ]);
    }

    private function saveSettings(): void
    {
        $payload = $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);

        $error = $this->validateSettingsInput();
        if ($error !== null) {
            $usersRepository = UsersRepository::getInstance();
            $metricsRepository = UserMetricsRepository::getInstance();
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

        $url = "http://{$_SERVER['HTTP_HOST']}/settings" . ($changed ? '?saved=1' : '');
        header("Location: {$url}");
        exit;
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
