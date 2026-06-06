<?php

declare(strict_types=1);

namespace Util;

/**
 * Generyczne narzędzia matematyczne używane w całym pluginie.
 *
 * Czego tu NIE ma i dlaczego:
 *   - Matematyka ELO         → Domain/Ranking/EloCalculator.php
 *   - Wektory knockbacku     → Combat/KnockbackEngine.php
 *   - Logika zasięgu uderzenia → Combat/HitValidator.php
 */
final class MathUtil {

    private function __construct() {}

    // -------------------------------------------------------------------------
    // Podstawowe
    // -------------------------------------------------------------------------

    public static function clamp(float $value, float $min, float $max): float {
        return max($min, min($max, $value));
    }

    /**
     * Liniowa interpolacja. t=0 zwraca $from, t=1 zwraca $to.
     * Używane np. do płynnego skalowania nagród w turniejach.
     */
    public static function lerp(float $from, float $to, float $t): float {
        return $from + self::clamp($t, 0.0, 1.0) * ($to - $from);
    }

    public static function roundTo(float $value, int $decimals = 2): float {
        return round($value, $decimals);
    }

    // -------------------------------------------------------------------------
    // Procenty i szanse
    // -------------------------------------------------------------------------

    /**
     * Oblicza procent z zachowaniem bezpieczeństwa przy dzieleniu przez 0.
     * Używane do accuracy, win rate, itp.
     *
     * MathUtil::percent(43, 100) → 43.0
     * MathUtil::percent(0, 0)    → 0.0
     */
    public static function percent(int $part, int $total, int $decimals = 1): float {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, $decimals);
    }

    /**
     * Rzut monetą z podanym prawdopodobieństwem (0.0 – 100.0).
     * Używane do losowych eventów, np. bonus drop po meczu.
     *
     * MathUtil::chance(25.0) → true z szansą 25%
     */
    public static function chance(float $percent): bool {
        return (mt_rand(0, 10_000) / 100) < self::clamp($percent, 0.0, 100.0);
    }

    // -------------------------------------------------------------------------
    // Geometria 3D (surowe floaty, bez zależności od PocketMine)
    // Dlatego HitValidator i KnockbackEngine mogą je testować bez serwera.
    // -------------------------------------------------------------------------

    public static function distance3D(
        float $x1, float $y1, float $z1,
        float $x2, float $y2, float $z2,
    ): float {
        return sqrt(
            ($x2 - $x1) ** 2 +
            ($y2 - $y1) ** 2 +
            ($z2 - $z1) ** 2
        );
    }

    public static function distance2D(
        float $x1, float $z1,
        float $x2, float $z2,
    ): float {
        return sqrt(($x2 - $x1) ** 2 + ($z2 - $z1) ** 2);
    }

    /**
     * Kąt (w stopniach) między wektorem patrzenia a kierunkiem do celu.
     * Używane przez HitValidator do sprawdzania, czy gracz faktycznie celował.
     *
     * @param float $yaw   Obrót poziomy patrzącego (stopnie)
     * @param float $pitch Obrót pionowy patrzącego (stopnie)
     * @param float $dx    Delta X do celu
     * @param float $dy    Delta Y do celu
     * @param float $dz    Delta Z do celu
     */
    public static function angleToDelta(
        float $yaw,
        float $pitch,
        float $dx,
        float $dy,
        float $dz,
    ): float {
        $length = sqrt($dx ** 2 + $dy ** 2 + $dz ** 2);

        if ($length < 1.0e-10) {
            return 0.0;
        }

        $yawRad   = deg2rad(-$yaw);
        $pitchRad = deg2rad(-$pitch);

        $lookX = -sin($yawRad) * cos($pitchRad);
        $lookY = -sin($pitchRad);
        $lookZ =  cos($yawRad) * cos($pitchRad);

        $dot = ($lookX * $dx + $lookY * $dy + $lookZ * $dz) / $length;

        return rad2deg(acos(self::clamp($dot, -1.0, 1.0)));
    }
}