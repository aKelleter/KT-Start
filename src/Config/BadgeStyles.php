<?php
declare(strict_types=1);

namespace App\Config;

final class BadgeStyles
{
    /** @return array<string, array{label: string, bg: string, light: string}> */
    public static function all(): array
    {
        return [
            'deepBlue'    => ['label' => 'Bleu foncé',   'bg' => '#1565C0', 'light' => '#90c4f8'],
            'deepPurple'  => ['label' => 'Violet foncé', 'bg' => '#4527A0', 'light' => '#b39ddb'],
            'lightViolet' => ['label' => 'Violet',        'bg' => '#7B1FA2', 'light' => '#e0aaee'],
            'lightBlue'   => ['label' => 'Bleu clair',   'bg' => '#0288D1', 'light' => '#80d8ff'],
            'turquoise'   => ['label' => 'Turquoise',     'bg' => '#00796B', 'light' => '#80cbc4'],
            'lightGreen'  => ['label' => 'Vert',          'bg' => '#388E3C', 'light' => '#a5d6a7'],
            'lightOrange' => ['label' => 'Orange',        'bg' => '#E65100', 'light' => '#ffd180'],
            'deepOrange'  => ['label' => 'Orange foncé', 'bg' => '#BF360C', 'light' => '#ffab91'],
            'red'         => ['label' => 'Rouge',         'bg' => '#C62828', 'light' => '#ffcdd2'],
            'pink'        => ['label' => 'Rose',          'bg' => '#AD1457', 'light' => '#f8bbd0'],
            'brown'       => ['label' => 'Brun',          'bg' => '#4E342E', 'light' => '#bcaaa4'],
            'grey'        => ['label' => 'Gris',          'bg' => '#546E7A', 'light' => '#b0bec5'],
        ];
    }

    public static function bg(string $style): string
    {
        return self::all()[$style]['bg'] ?? '#1565C0';
    }

    public static function gradient(string $style): string
    {
        $s = self::all()[$style] ?? self::all()['deepBlue'];
        return "linear-gradient(135deg, {$s['bg']} 0%, {$s['light']} 100%)";
    }
}
