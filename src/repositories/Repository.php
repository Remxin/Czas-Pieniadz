<?php

require_once __DIR__."/../../Database.php";

abstract class Repository {

    /** @var array<string, static> */
    private static array $instances = [];

    protected $database;

    public static function getInstance(): static
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        return self::$instances[$class];
    }

    protected function __construct() {
        $this->database = new Database();
    }
}