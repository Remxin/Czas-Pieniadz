<?php

require_once 'AppController.php';

class DashboardController extends AppController {

    public function index(?string $id = null) {
        $this->requireAuth();
        return $this->render('dashboard');
    }

    public function settings() {
        $this->requireAuth();
        return $this->render('settings');
    }

    public function history() {
        $this->requireAuth();
        return $this->render('history');
    }
}
