<?php

require_once 'AppController.php';
require_once __DIR__.'/../repositories/UsersRepository.php';

class DashboardController extends AppController {

    public function index(?string $id = null) {
        $auth = $this->requireAuth();
        $currentUser = [
            'id' => $auth['sub'] ?? '',
            'email' => $auth['email'] ?? '',
        ];

        $title = "INDEX";

        $usersRepository = UsersRepository::getInstance();
        $users = $usersRepository->getUsers();
        return $this->render("index", [
            "title" => $title,
            "users" => $users,
            "currentUser" => $currentUser,
        ]);
    }
}