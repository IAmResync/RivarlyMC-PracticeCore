<?php

declare(strict_types=1);

namespace Util;

/**
 * Narzędzia do pracy z czasem.
 * Odpowiedzialność: konwersja i formatowanie. Nic więcej.
 * Kolory, prefixy, suffixy — to robota warstwy UI, nie tej klasy.
 */
final class TimeUtil {

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Konwersja
    // -------------------------------------------------------------------------

    public static function secondsToTicks(float $seconds): int {
        return (int) ($seconds * 20);
    }

    public static function ticksToSeconds(int $ticks): float {
        return $ticks / 20;
    }

    public static function secondsSince(int $timestamp): int {
        return max(0, time() - $timestamp);
    }

    public static function hasElapsed(int $timestamp, int $seconds): bool {
        return self::secondsSince($timestamp) >= $seconds;
    }

    // -------------------------------------------------------------------------
    // Formatowanie
    // -------------------------------------------------------------------------

    /**
     * Krótki format zegara: 02:30
     * Używany w timerach meczowych, odliczaniu.
     */
    public static function clock(int $seconds): string {
        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Czytelny format dla gracza: "2 min 30 s", "45 s", "1 h 5 min"
     * Używany w podsumowaniach meczu, profilu gracza.
     */
    public static function humanReadable(int $seconds): string {
        if ($seconds < 60) {
            return "{$seconds} s";
        }

        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs    = $seconds % 60;

        if ($hours > 0) {
            return $minutes > 0
                ? "{$hours} h {$minutes} min"
                : "{$hours} h";
        }

        return $secs > 0
            ? "{$minutes} min {$secs} s"
            : "{$minutes} min";
    }

    /**
     * Tylko minuty (zaokrąglone w dół): "2 min"
     * Używany w statystykach gdzie sekundy są bez znaczenia.
     */
    public static function minutes(int $seconds): string {
        return intdiv($seconds, 60) . ' min';
    }
}