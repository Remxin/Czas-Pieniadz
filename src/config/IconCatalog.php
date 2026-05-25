<?php

class IconCatalog
{
    public const DEFAULT_ICON = 'shopping_bag';

    /** @var array<string, array{label: string, theme: string}> */
    private const ICONS = [
        'shopping_bag' => ['label' => 'Zakupy', 'theme' => 'primary'],
        'laptop_mac' => ['label' => 'Elektronika', 'theme' => 'indigo'],
        'restaurant' => ['label' => 'Jedzenie', 'theme' => 'orange'],
        'local_cafe' => ['label' => 'Kawiarnia', 'theme' => 'yellow'],
        'directions_car' => ['label' => 'Transport', 'theme' => 'blue'],
        'home' => ['label' => 'Dom', 'theme' => 'emerald'],
        'fitness_center' => ['label' => 'Sport', 'theme' => 'purple'],
        'flight' => ['label' => 'Podróże', 'theme' => 'indigo'],
    ];

    /**
     * @return array<string, array{label: string, theme: string}>
     */
    public static function all(): array
    {
        return self::ICONS;
    }

    public static function isAllowed(string $icon): bool
    {
        return array_key_exists($icon, self::ICONS);
    }

    public static function normalize(string $icon): string
    {
        return self::isAllowed($icon) ? $icon : self::DEFAULT_ICON;
    }

    public static function themeClass(string $icon): string
    {
        $theme = self::ICONS[self::normalize($icon)]['theme'] ?? 'primary';

        return 'app-expense-item__icon--' . $theme;
    }

    public static function label(string $icon): string
    {
        return self::ICONS[self::normalize($icon)]['label'] ?? 'Wydatek';
    }
}
