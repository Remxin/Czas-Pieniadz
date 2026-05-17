<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class SecurityController extends AppController {

    public function login() {
        if (!$this->isPost()) {
            if ($this->getJwtPayload() !== null) {
                $url = "http://$_SERVER[HTTP_HOST]/dashboard";
                header("Location: {$url}");
                exit;
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

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/dashboard");
        exit;
    }

    public function logout() {
        setcookie(
            $this->jwtCookieName(),
            '',
            $this->authCookieOptions(time() - 3600)
        );
        $url = "http://$_SERVER[HTTP_HOST]/login";
        header("Location: {$url}");
        exit;
    }

    public function register() {
        if (!$this->isPost()) {
            return $this->render("register");
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $username = $_POST['username'] ?? '';

        if (empty($email) || empty($password) || empty($username)) {
            return $this->render('register', ['messages' => 'Fill all fields']);
        }

        $usersRepository = UsersRepository::getInstance();

        $user = $usersRepository->getUserByEmail($_POST['email']);
        if ($user) {
            return $this->render("register", ["messages" => "User already exists"]);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $usersRepository->createUser(
            $email,
            $hashedPassword,
            $username,
        );

        $url = "http://$_SERVER[HTTP_HOST]";
        header("Location: {$url}/login");
    }
}