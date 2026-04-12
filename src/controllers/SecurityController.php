<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class SecurityController extends AppController {

    public function login() {
        // TODO sprawdzeie czy user istnieje

        if ($this->isPost()) {
            // return $this->render("dashboard");

            $usersRepository = new UsersRepository();
            $user = $usersRepository->getUserByEmail($_POST['email']);
            if (!$user) {
                return $this->render("login", ["messages" => "User not found"]);
            }

            if ($user['password'] !== $_POST['password']) {
                return $this->render("login", ["messages" => "Invalid password"]);
            }
            //

            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/dashboard");
        }

        return $this->render("login");
    }

    public function register() {
        if ($this->isPost()) {
            $usersRepository = new UsersRepository();

            $user = $usersRepository->getUserByEmail($_POST['email']);
            if ($user) {
                return $this->render("register", ["messages" => "User already exists"]);
            }

            $usersRepository->createUser(
                $_POST['email'],
                $_POST['password'],
                $_POST['username']
            );

            $url = "http://$_SERVER[HTTP_HOST]";
            header("Location: {$url}/login");
        }

        return $this->render("register");
    }
}