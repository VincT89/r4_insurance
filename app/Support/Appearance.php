<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Appearance
{
    public const FIELDS = [
        'sidebar' => ['label' => 'Sidebar', 'group' => 'Navigazione', 'default' => '#18181d'],
        'sidebar_active' => ['label' => 'Voce selezionata nella sidebar', 'group' => 'Navigazione', 'default' => '#0b498c'],
        'topbar' => ['label' => 'Barra superiore', 'group' => 'Navigazione', 'default' => '#111115'],
        'page' => ['label' => 'Sfondo generale', 'group' => 'Contenuto', 'default' => '#ffffff'],
        'panel' => ['label' => 'Pannelli e schede', 'group' => 'Contenuto', 'default' => '#ffffff'],
        'field' => ['label' => 'Campi dei moduli', 'group' => 'Contenuto', 'default' => '#ffffff'],
        'table_header' => ['label' => 'Intestazioni delle tabelle', 'group' => 'Tabelle', 'default' => '#0b498c'],
        'table_body' => ['label' => 'Righe delle tabelle', 'group' => 'Tabelle', 'default' => '#ffffff'],
        'table_hover' => ['label' => 'Riga al passaggio del puntatore', 'group' => 'Tabelle', 'default' => '#e2edf8'],
        'primary' => ['label' => 'Pulsanti principali e accenti', 'group' => 'Testi e azioni', 'default' => '#0b498c'],
        'form_border' => ['label' => 'Bordi dei riquadri dei moduli', 'group' => 'Contenuto', 'default' => '#0b498c'],
        'heading' => ['label' => 'Titoli e collegamenti', 'group' => 'Testi e azioni', 'default' => '#0b498c'],
        'text' => ['label' => 'Testo preferito', 'group' => 'Testi e azioni', 'default' => '#1a1a2e'],
        'sidebar_hover' => ['label' => 'Voce del menu al passaggio', 'group' => 'Passaggio del puntatore', 'default' => '#26262e'],
        'primary_hover' => ['label' => 'Pulsanti principali al passaggio', 'group' => 'Passaggio del puntatore', 'default' => '#083b72'],
        'secondary_hover' => ['label' => 'Pulsanti secondari al passaggio', 'group' => 'Passaggio del puntatore', 'default' => '#d8e7f6'],
        'link_hover' => ['label' => 'Collegamenti al passaggio', 'group' => 'Passaggio del puntatore', 'default' => '#083b72'],
        'login_panel' => ['label' => 'Sfondo del modulo di accesso', 'group' => 'Login', 'default' => '#000000'],
    ];

    public static function defaults(): array
    {
        return array_map(fn ($field) => $field['default'], self::FIELDS);
    }

    public static function forRequest(Request $request): array
    {
        if ($user = $request->user()) {
            return self::colors($user->appearance);
        }
        $cookie = $request->cookie('appearance_theme');
        $decoded = is_string($cookie) ? json_decode($cookie, true) : null;

        return self::colors(is_array($decoded) ? $decoded : null);
    }

    public static function colors(?array $stored): array
    {
        $colors = self::defaults();
        foreach ($colors as $key => $default) {
            $value = $stored[$key] ?? null;
            if (is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value)) {
                $colors[$key] = strtolower($value);
            }
        }

        if (!isset($stored['form_border'])) $colors['form_border'] = $colors['primary'];
        return $colors;
    }

    public static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(substr($hex, 1), 2));
    }

    private static function luminance(string $hex): float
    {
        $values = array_map(function ($channel) {
            $channel /= 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return $values[0] * 0.2126 + $values[1] * 0.7152 + $values[2] * 0.0722;
    }

    public static function contrast(string $a, string $b): float
    {
        $a = self::luminance($a);
        $b = self::luminance($b);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function readable(string $preferred, string $background, float $minimum = 4.5): string
    {
        if (self::contrast($preferred, $background) >= $minimum) {
            return $preferred;
        }

        return self::contrast('#000000', $background) >= self::contrast('#ffffff', $background) ? '#000000' : '#ffffff';
    }

    private static function mix(string $a, string $b, float $weight): string
    {
        $b = self::rgb($b);
        $rgb = array_map(fn ($v, $i) => (int) round($v * $weight + $b[$i] * (1 - $weight)), self::rgb($a), [0, 1, 2]);

        return sprintf('#%02x%02x%02x', ...$rgb);
    }

    public static function variables(?array $stored): array
    {
        $colors = self::colors($stored);
        /** Le superfici di sezione seguono il colore personale e mantengono un contrasto calcolato. */
        $colors['section'] = self::mix($colors['primary'], $colors['panel'], 0.16);
        $colors['section_end'] = self::mix($colors['primary'], $colors['panel'], 0.04);
        $vars = [];
        foreach (array_diff(array_keys($colors), ['primary', 'primary_hover', 'heading', 'link_hover', 'text']) as $key) {
            $background = $colors[$key];
            $prefix = '--ap-'.str_replace('_', '-', $key);
            $text = self::readable($colors['text'], $background);
            $vars[$prefix] = $background;
            $vars[$prefix.'-text'] = $text;
            $vars[$prefix.'-muted'] = self::readable(self::mix($text, $background, 0.72), $background);
            $vars[$prefix.'-line'] = self::mix($text, $background, 0.2);
            $vars[$prefix.'-border'] = self::readable(self::mix($text, $background, 0.55), $background, 3);
            $vars[$prefix.'-soft'] = self::mix($background, $text, 0.94);
            $vars[$prefix.'-heading'] = self::readable($colors['heading'], $background);
            $vars[$prefix.'-link-hover'] = self::readable($colors['link_hover'], $background);
            $vars[$prefix.'-accent'] = self::readable($colors['primary'], $background);
            foreach (['red' => '#b42323', 'green' => '#147a52', 'blue' => '#2459b8', 'orange' => '#a33b00', 'purple' => '#6d4db7', 'teal' => '#0f766e', 'yellow' => '#854d0e'] as $name => $color) {
                $vars[$prefix.'-'.$name] = self::readable($color, $background);
            }
        }
        $vars['--ap-primary'] = $colors['primary'];
        $vars['--ap-primary-text'] = self::readable('#ffffff', $colors['primary']);
        $vars['--ap-primary-hover'] = $colors['primary_hover'];
        $vars['--ap-primary-hover-text'] = self::readable($vars['--ap-primary-text'], $vars['--ap-primary-hover']);
        $vars['--ap-primary-rgb'] = implode(', ', self::rgb($colors['primary']));
        /** A personal palette must remain readable across both ends of the heading gradient. */
        $vars['--ap-section-gradient-end'] = self::contrast($vars['--ap-section-heading'], $colors['section_end']) >= 4.5 ? $colors['section_end'] : $colors['section'];

        return $vars;
    }

    public static function style(?array $stored): string
    {
        $css = '';
        foreach (self::variables($stored) as $name => $value) {
            $css .= $name.':'.$value.';';
        }

        return $css;
    }
}
