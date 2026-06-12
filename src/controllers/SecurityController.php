<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../services/AuthTokenService.php';
require_once __DIR__ . '/../services/CsrfService.php';
require_once __DIR__ . '/../services/LoginAuditLogger.php';

class SecurityController extends AppController {

    private const LOGIN_FAILED_MESSAGE = 'Niepoprawny adres e-mail lub hasło.';

    /** Bcrypt dummy hash – stały czas weryfikacji gdy użytkownik nie istnieje. */
    private const DUMMY_PASSWORD_HASH = '$2y$10$usesomesillystringfore7hnbRJHxXVLEJaGFtj8FE0Ru0';

    public function login() {
        if (!$this->isPost()) {
            $payload = $this->getJwtPayload();
            if ($payload !== null) {
                $userId = $this->userIdFromPayload($payload);
                $user = UsersRepository::getInstance()->getUserById($userId);
                if ($user !== null) {
                    $this->redirectAfterAuth($userId);
                }
                $this->clearAuthCookie();
            }
            return $this->renderLogin();
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!CsrfService::getInstance()->validateToken($csrfToken)) {
            LoginAuditLogger::logFailedAttempt($email, 'csrf_invalid');
            return $this->renderLogin(['messages' => 'Nieprawidłowe żądanie. Spróbuj ponownie.']);
        }

        if ($email === '' || $password === '') {
            LoginAuditLogger::logFailedAttempt($email, 'empty_fields');
            return $this->renderLogin(['messages' => 'Wypełnij wszystkie pola.']);
        }

        $usersRepository = UsersRepository::getInstance();
        $user = $usersRepository->getUserByEmail($email);

        if ($user === null) {
            password_verify($password, self::DUMMY_PASSWORD_HASH);
            LoginAuditLogger::logFailedAttempt($email, 'user_not_found');
            return $this->renderLogin(['messages' => self::LOGIN_FAILED_MESSAGE]);
        }

        if (!password_verify($password, $user['password'])) {
            LoginAuditLogger::logFailedAttempt($email, 'wrong_password');
            return $this->renderLogin(['messages' => self::LOGIN_FAILED_MESSAGE]);
        }

        $rememberMe = !empty($_POST['remember_me']);
        $auth = AuthTokenService::getInstance();
        $auth->revokeCurrentSession();
        $issued = $auth->issueTokens((int) $user['id'], (string) $user['email'], $rememberMe);
        $auth->setAuthCookies(
            $issued['accessJwt'],
            $issued['refreshToken'],
            $issued['refreshExpires']
        );

        $this->redirectAfterAuth((int) $user['id']);
    }

    public function logout() {
        AuthTokenService::getInstance()->revokeCurrentSession();
        $this->redirectTo('/login');
    }

    public function logoutAll() {
        if (!$this->isPost()) {
            $this->redirectTo('/settings');
        }

        $payload = $this->requireAuth();
        $userId = $this->userIdFromPayload($payload);
        AuthTokenService::getInstance()->revokeAllSessionsForUser($userId);
        $this->redirectTo('/login');
    }

    public function refresh() {
        $this->consumeJsonBody();
        $payload = AuthTokenService::getInstance()->tryRefreshFromCookie();
        if ($payload === null) {
            AuthTokenService::getInstance()->clearAuthCookies();
            $this->json(['ok' => false, 'message' => 'Sesja wygasła. Zaloguj się ponownie.'], 401);
        }

        $this->json(['ok' => true]);
    }

    public function register() {
        if (!$this->isPost()) {
            return $this->render("register");
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $username = $_POST['username'] ?? '';

        if (empty($email) || empty($password) || empty($username)) {
            return $this->render('register', ['messages' => 'Fill all fields']);
        }

        $passwordError = $this->validatePasswordStrength($password);
        if ($passwordError !== null) {
            return $this->render('register', ['messages' => $passwordError]);
        }

        if ($password !== $password2) {
            return $this->render('register', ['messages' => 'Passwords do not match']);
        }

        $usersRepository = UsersRepository::getInstance();

        $user = $usersRepository->getUserByEmail($email);
        if ($user) {
            return $this->render("register", ["messages" => "User already exists"]);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $usersRepository->createUser(
            $email,
            $hashedPassword,
            $username,
        );

        $this->redirectTo('/login');
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderLogin(array $variables = []): void
    {
        $variables['csrfToken'] = CsrfService::getInstance()->generateToken();
        $this->render('login', $variables);
    }

    /**
     * @return non-empty-string|null Error message, or null if valid.
     */
    private function validatePasswordStrength(string $password): ?string
    {
        $len = strlen($password);
        if ($len < 8) {
            return 'Hasło musi mieć co najmniej 8 znaków.';
        }
        if ($len > 72) {
            return 'Hasło może mieć maksymalnie 72 znaki.';
        }
        return null;
    }
}
