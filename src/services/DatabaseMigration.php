<?php

class DatabaseMigration
{
    private static bool $ran = false;

    public static function run(PDO $connection): void
    {
        if (self::$ran) {
            return;
        }
        self::$ran = true;

        $connection->exec(
            "
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_enum e
                    JOIN pg_type t ON e.enumtypid = t.oid
                    WHERE t.typname = 'lifecycle_metric_type'
                      AND e.enumlabel = 'work_hours_per_month'
                ) THEN
                    ALTER TYPE lifecycle_metric_type ADD VALUE 'work_hours_per_month';
                END IF;
            END
            $$;
            "
        );
    }
}
