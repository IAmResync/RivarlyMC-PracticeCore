<?php

declare(strict_types=1);

namespace AntiCheat;

use Config\PluginConfig;

/**
 * Śledzi clicks-per-second każdego gracza w rolling window 1000ms.
 * Używany przez HitValidator do wykrywania autoclickerów.
 */
final class CpsLimiter {

    private const WINDOW_MS = 1000;

    private int $maxCps;

    /** @var array<string, list<float>> uuid => lista microtime() */
    private array $clickWindows = [];

    public function __construct(PluginConfig $config) {
        // NAPRAWIONO: Pobieranie wartości z PluginConfig
        $this->maxCps = $config->getMaxCps();
    }

    // -----------------------------------------------------------------------
    // Publiczne API
    // -----------------------------------------------------------------------

    /**
     * Rejestruje kliknięcie i zwraca aktualny CPS gracza.
     * Automatycznie oczyszcza tablicę z kliknięć starszych niż 1 sekunda.
     */
    public function recordClick(string $uuid): int {
        $now = microtime(true);
        $cutoff = $now - (self::WINDOW_MS / 1000.0);

        if (!isset($this->clickWindows[$uuid])) {
            $this->clickWindows[$uuid] = [];
        }

        // ZOPTYMALIZOWANO: Zamiast powolnego array_filter/array_values,
        // usuwamy najstarsze elementy z początku za pomocą szybkiego array_shift.
        while (!empty($this->clickWindows[$uuid]) && $this->clickWindows[$uuid][0] < $cutoff) {
            array_shift($this->clickWindows[$uuid]);
        }

        // Dodaj aktualne kliknięcie na koniec kolejki
        $this->clickWindows[$uuid][] = $now;

        return count($this->clickWindows[$uuid]);
    }

    /**
     * Zwraca aktualny CPS gracza bez dodawania nowego kliknięcia.
     */
    public function getCurrentCps(string $uuid): int {
        if (!isset($this->clickWindows[$uuid]) || empty($this->clickWindows[$uuid])) {
            return 0;
        }

        $now = microtime(true);
        $cutoff = $now - (self::WINDOW_MS / 1000.0);

        // Oczyszczamy okno przy zapytaniu bez mutowania przez array_filter
        $validClicks = 0;
        foreach ($this->clickWindows[$uuid] as $timestamp) {
            if ($timestamp >= $cutoff) {
                $validClicks++;
            }
        }

        return $validClicks;
    }

    /**
     * Czy gracz aktualnie przekracza limit CPS (podejrzany o autoclicker).
     */
    public function isSuspicious(string $uuid): bool {
        return $this->getCurrentCps($uuid) > $this->maxCps;
    }

    /**
     * Jak bardzo gracz przekracza limit (0.0 = ok, 1.5 = 50% powyżej limitu).
     * Używane przez FlagLogger do oceny ciężkości flagi.
     */
    public function getViolationMultiplier(string $uuid): float {
        $cps = $this->getCurrentCps($uuid);
        if ($cps <= $this->maxCps) {
            return 0.0;
        }
        return round($cps / $this->maxCps, 2);
    }

    /**
     * Czyści dane gracza po wylogowaniu lub zakończeniu meczu.
     */
    public function clearPlayer(string $uuid): void {
        unset($this->clickWindows[$uuid]);
    }

    /**
     * Resetuje wszystkich graczy (np. przy restarcie serwera).
     */
    public function clearAll(): void {
        $this->clickWindows = [];
    }
}