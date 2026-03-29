<?php
declare(strict_types=1);

namespace App\Config;

final class BadgeStyles
{
    /** @return array<string, array{label: string, bg: string}> */
    public static function all(): array
    {
        return [
            'deepBlue'    => ['label' => 'Bleu foncé',   'bg' => '#1565C0'],
            'deepPurple'  => ['label' => 'Violet foncé', 'bg' => '#4527A0'],
            'lightViolet' => ['label' => 'Violet',        'bg' => '#7B1FA2'],
            'lightBlue'   => ['label' => 'Bleu clair',   'bg' => '#0288D1'],
            'turquoise'   => ['label' => 'Turquoise',     'bg' => '#00796B'],
            'lightGreen'  => ['label' => 'Vert',          'bg' => '#388E3C'],
            'lightOrange' => ['label' => 'Orange',        'bg' => '#E65100'],
            'deepOrange'  => ['label' => 'Orange foncé', 'bg' => '#BF360C'],
            'red'         => ['label' => 'Rouge',         'bg' => '#C62828'],
            'pink'        => ['label' => 'Rose',          'bg' => '#AD1457'],
            'brown'       => ['label' => 'Brun',          'bg' => '#4E342E'],
            'grey'        => ['label' => 'Gris',          'bg' => '#546E7A'],
        ];
    }

    public static function bg(string $style): string
    {
        return self::all()[$style]['bg'] ?? '#1565C0';
    }
}
