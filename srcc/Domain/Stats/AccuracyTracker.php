<?php

declare(strict_types=1);

namespace Domain\Stats;

/**
 * Przelicza i śledzi celność uderzeń gracza na podstawie zarejestrowanych akcji.
 * Chroni silnik przed błędami matematycznymi i anomaliami sieciowymi pakietów Minecraft.
 */
final class AccuracyTracker {

    private int $swings;
    private int $hits;

    public function __construct() {
        $this->swings = 0;
        $this->hits = 0;
    }

    public function recordSwing(): void {
        $this->swings++;
    }

    public function recordHit(): void {
        $this->hits++;

        // Zabezpieczenie na wypadek, gdyby pakiet uderzenia dotarł do serwera przed pakietem machnięcia
        if ($this->hits > $this->swings) {
            $this->swings = $this->hits;
        }
    }

    public function getSwings(): int {
        return $this->swings;
    }

    public function getHits(): int {
        return $this->hits;
    }

    /**
     * Zwraca liczbę nieudanych/chybionych uderzeń.
     */
    public function getMissed(): int {
        return max(0, $this->swings - $this->hits);
    }

    /**
     * Zwraca procentową celność gracza (0.0 - 100.0).
     */
    public function getAccuracy(): float {
        if ($this->swings === 0) {
            return 0.0;
        }

        $accuracy = round(($this->hits / $this->swings) * 100, 2);

        // Bezpiecznik gwarantujący, że celność nigdy nie przekroczy logicznych 100%
        return min(100.0, $accuracy);
    }

    /**
     * Resetuje licznik (przydatne przy ponownym wykorzystaniu trackera).
     */
    public function reset(): void {
        $this->swings = 0;
        $this->hits = 0;
    }
}