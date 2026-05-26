<?php

require_once 'AppController.php';
require_once __DIR__ . '/../repositories/UsersRepository.php';
require_once __DIR__ . '/../services/AuthTokenService.php';

class SecurityController extends AppController {

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
            return $this->render("login");
        }

        $email = $_POST["email"] ?? '';
        $password = $_POST["password"] ?? '';

        if (empty($email) || empty($password)) {
            return $this->render('login', ['messages' => 'Fill all fields']);
        }

        $usersRepository = UsersRepository::getInstance();
        $user = $usersRepository->getUserByEmail($_POST['email']);
        if (!$user) {
            return $this->render("login", ["messages" => "User not found"]);
        }
        
        if (!password_verify($password, $user['password'])) {
            return $this->render('login', ['messages' => 'Wrong password']);
        }

        $rememberMe = !empty($_POST['remember_me']);
        $auth = AuthTokenService::getInstance();
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
     * @return non-empty-string|null Error message, or null if valid.
     */
    private function validatePasswordStrength(string $password): ?string
    {
        $len = strlen($password);
        if ($len < 4) {
            return 'Password must be at least 4 characters long.';
        }
        if ($len > 72) {
            return 'Password must be at most 72 characters long.';
        }
        return null;
    }
}
