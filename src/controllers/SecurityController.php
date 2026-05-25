<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

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

        $jwt = JwtService::encode([
            'sub' => (string) $user['id'],
            'email' => $user['email'],
        ]);

        setcookie(
            $this->jwtCookieName(),
            $jwt,
            $this->authCookieOptions(time() + (defined('JWT_TTL') ? (int) JWT_TTL : 604800))
        );

        $this->redirectAfterAuth((int) $user['id']);
    }

    public function logout() {
        setcookie(
            $this->jwtCookieName(),
            '',
            $this->authCookieOptions(time() - 3600)
        );
        $this->redirectTo('/login');
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