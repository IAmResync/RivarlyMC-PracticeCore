<?php

declare(strict_types=1);

namespace Util;

use pocketmine\utils\TextFormat;

/**
 * Narzędzia do formatowania tekstu i liczb.
 *
 * Czego tu NIE ma i dlaczego:
 *   - Teksty wiadomości       → każdy Manager/klasa trzyma swoje stringi
 *   - Logika rang i kolorów   → Domain/Player/Division.php (metoda ->color())
 *   - Formatowanie czasu      → TimeUtil
 */
final class FormatterUtil {

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Kolory i styl
    // -------------------------------------------------------------------------

    /**
     * Zamienia skróty &x na kody §x (Minecraft color codes).
     * Używane gdy tekst pochodzi z config.yml lub zewnętrznego źródła.
     *
     * FormatterUtil::colorize("&aHello &lWorld") → "§aHello §lWorld"
     */
    public static function colorize(string $text): string {
        return TextFormat::colorize($text);
    }

    public static function strip(string $text): string {
        return TextFormat::clean($text);
    }

    // -------------------------------------------------------------------------
    // Liczby
    // -------------------------------------------------------------------------

    /**
     * Formatuje duże liczby z separatorem tysięcy.
     * Używane wszędzie gdzie wyświetlasz statystyki graczowi.
     *
     * FormatterUtil::number(1234567) → "1,234,567"
     */
    public static function number(int|float $value): string {
        return number_format($value, 0, '.', ',');
    }

    /**
     * Skraca duże liczby do czytelnej formy.
     * Używane w scoreboard / leaderboardzie gdzie miejsce jest ograniczone.
     *
     * FormatterUtil::compact(1500)    → "1.5K"
     * FormatterUtil::compact(2300000) → "2.3M"
     */
    public static function compact(int|float $value): string {
        return match (true) {
            $value >= 1_000_000 => round($value / 1_000_000, 1) . 'M',
            $value >= 1_000     => round($value / 1_000, 1) . 'K',
            default             => (string) $value,
        };
    }

    // -------------------------------------------------------------------------
    // Szablony wiadomości
    // -------------------------------------------------------------------------

    /**
     * Podmienia placeholdery {klucz} na wartości z tablicy.
     * Używane przez każdy Manager który buduje wiadomość dla gracza.
     *
     * FormatterUtil::fill(
     *     "{player} won against {opponent} ({elo} ELO)",
     *     ['player' => 'Steve', 'opponent' => 'Alex', 'elo' => 1420]
     * )
     * → "Steve won against Alex (1,420 ELO)"
     *
     * Liczby całkowite są automatycznie formatowane przez number().
     */
    public static function fill(string $template, array $values): string {
        $search  = [];
        $replace = [];

        foreach ($values as $key => $value) {
            $search[]  = '{' . $key . '}';
            $replace[] = is_int($value) ? self::number($value) : (string) $value;
        }

        return str_replace($search, $replace, $template);
    }

    // -------------------------------------------------------------------------
    // UI
    // -------------------------------------------------------------------------

    /**
     * Generuje pasek postępu zbudowany ze znaków.
     * Używane w scoreboard, np. do wizualizacji ELO w aktualnej dywizji.
     *
     * FormatterUtil::progressBar(6, 10)
     * → "§a██████§7████"
     *
     * @param int    $current     Aktualna wartość
     * @param int    $max         Maksymalna wartość
     * @param int    $length      Długość paska w znakach
     * @param string $filledColor Kod koloru wypełnionej części
     * @param string $emptyColor  Kod koloru pustej części
     */
    public static function progressBar(
        int $current,
        int $max,
        int $length = 10,
        string $filledColor = TextFormat::GREEN,
        string $emptyColor = TextFormat::DARK_GRAY,
    ): string {
        if ($max <= 0) {
            return $emptyColor . str_repeat('█', $length);
        }

        $filled = (int) round(MathUtil::clamp($current / $max, 0.0, 1.0) * $length);
        $empty  = $length - $filled;

        return $filledColor . str_repeat('█', $filled)
             . $emptyColor  . str_repeat('█', $empty);
    }
}