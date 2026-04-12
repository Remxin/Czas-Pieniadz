<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class SecurityController extends AppController {

    public function login() {
        // TODO sprawdzeie czy user istnieje

        if ($this->isPost()) {
            $email = $_POST["email"] ?? '';
            $password = $_POST["password"] ?? '';
    
    // var_dump($email);
    
            if (empty($email) || empty($password)) {
                return $this->render('login', ['messages' => 'Fill all fields']);
            }
    
            $usersRepository = new UsersRepository();
            $user = $usersRepository->getUserByEmail($_POST['email']);
            if (!$user) {
                return $this->render("login", ["messages" => "User not found"]);
            }
          
            if (!password_verify($password, $user['password'])) {
                return $this->render('login', ['messages' => 'Wrong password']);
            }
    
    
            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
    
        }

        return $this->render("login");
    }

    public function register() {
        if ($this->isPost()) {

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $username = $_POST['username'] ?? '';
    
            if (empty($email) || empty($password) || empty($username)) {
                return $this->render('register', ['messages' => 'Fill all fields']);
            }

            $usersRepository = new UsersRepository();

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

        return $this->render("register");
    }
}