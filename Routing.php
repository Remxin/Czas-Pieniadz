<?php

require_once 'src/controllers/SecurityController.php';
require_once 'src/controllers/DashboardController.php';

class Routing {

    public static $routes = [
        "login" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
        "register" => [
            "controller" => "SecurityController",
            "action" => "register"
        ],
        "dashboard" => [
            "controller" => "DashboardController",
            "action" => "index"
        ],
        "" => [
            "controller" => "SecurityController",
            "action" => "login"
        ],
    ];

    protected static $controllerInstances = [];

    protected static function getControllerInstance(string $controller) {
        if(!isset(self::$controllerInstances[$controller])) {
            self::$controllerInstances[$controller] = new $controller();
        }
        return self::$controllerInstances[$controller];
    }

    public static function run(string $path) {
        // Regex to handle /dashboard and /dashboard/1234 style routes
        $matched = false;
        $routeKey = null;
        $id = null;

        if (preg_match('#^dashboard/(.+)$#', $path, $matches)) {
            $routeKey = 'dashboard';
            $id = $matches[1];
            $matched = true;
        }
        // Handle /dashboard
        else if ($path === 'dashboard') {
            $routeKey = 'dashboard';
            $matched = true;
        } 
        // Handle /login
        else if ($path === 'login') {
            $routeKey = 'login';
            $matched = true;
        } 
        // Handle /register
        else if ($path === 'register') {
            $routeKey = 'register';
            $matched = true;
        } 
        // Handle /
        else if ($path === '') {
            $routeKey = '';
            $matched = true;
        }

        if ($matched && array_key_exists($routeKey, self::$routes)) {
            $controller = self::$routes[$routeKey]["controller"];
            $action = self::$routes[$routeKey]["action"];
            $controllerObj = self::getControllerInstance($controller);
            $controllerObj->$action($id);
        } else {
            include 'public/views/404.html';
        }
    }
}