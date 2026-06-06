<?php

declare(strict_types=1);

namespace Application\Match;

use Domain\Match\GameMatch; // NAPRAWIONO: Używamy bezpiecznej klasy GameMatch
use Domain\Match\MatchState;
use Config\PluginConfig;

/**
 * Zarządza cyklem życia pojedynczego meczu – od odliczania do zakończenia.
 * Jedna instancja na jeden mecz, tworzona przez MatchManager::createMatch().
 *
 * Fazy:
 *   WAITING     → gracze teleportowani, zamrożeni
 *   STARTING    → odliczanie (countdownSeconds)
 *   ACTIVE      → walka (grace period na początku – brak obrażeń przez X sekund)
 *   ENDING      → mecz zakończony, cleanup
 *
 * MatchTickTask wywołuje tick() co 1 sekundę.
 * Każda faza zmienia stan GameMatch i wywołuje callback (np. wysłanie tytułu do graczy).
 *
 * Użycie:
 *   $lifecycle = new MatchLifecycle($match, $config, onCountdown: ..., onStart: ..., onEnd: ...);
 *   // MatchTickTask:
 *   $lifecycle->tick();
 */
final class MatchLifecycle {

    private int $ticksElapsed = 0;
    private int $countdownLeft;
    private int $gracePeriodLeft;
    private bool $finished = false;

    /** @var callable(int $secondsLeft): void */
    private $onCountdownTick;

    /** @var callable(): void */
    private $onMatchStart;

    /** @var callable(): void */
    private $onGracePeriodEnd;

    /** @var callable(): void – timeout (czas meczu upłynął bez rozstrzygnięcia) */
    private $onTimeout;

    private int $matchDurationSeconds;
    private int $activeSecondsElapsed = 0;

    /**
     * @param callable(int): void $onCountdownTick   Wywoływany co sekundę z ilością sekund do startu
     * @param callable(): void    $onMatchStart       Wywoływany gdy odliczanie dobiega końca (gracze mogą się ruszać)
     * @param callable(): void    $onGracePeriodEnd   Wywoływany gdy grace period się kończy (PvP włączone)
     * @param callable(): void    $onTimeout          Wywoływany gdy czas meczu upłynął (remis/losowy wynik)
     */
    public function __construct(
        private readonly GameMatch $match, // NAPRAWIONO: Zmiana typu na GameMatch
        private readonly PluginConfig $config,
        callable $onCountdownTick,
        callable $onMatchStart,
        callable $onGracePeriodEnd,
        callable $onTimeout,
    ) {
        $this->countdownLeft        = $config->getCountdownSeconds();
        $this->gracePeriodLeft      = $config->getGracePeriodSeconds();
        $this->matchDurationSeconds = $config->getMatchDurationSeconds();

        $this->onCountdownTick  = $onCountdownTick;
        $this->onMatchStart     = $onMatchStart;
        $this->onGracePeriodEnd = $onGracePeriodEnd;
        $this->onTimeout        = $onTimeout;
    }

    // -----------------------------------------------------------------------
    // Tick – wywoływany co 1 sekundę przez MatchTickTask
    // -----------------------------------------------------------------------

    public function tick(): void {
        if ($this->finished) {
            return;
        }

        $this->ticksElapsed++;

        // NAPRAWIONO: Struktura match expression działa teraz idealnie, bo klasa Match nie blokuje parsera
        match ($this->match->getState()) {
            MatchState::WAITING  => $this->tickWaiting(),
            MatchState::STARTING => $this->tickStarting(),
            MatchState::ACTIVE   => $this->tickActive(),
            MatchState::ENDING   => null,
        };
    }

    // -----------------------------------------------------------------------
    // Fazy
    // -----------------------------------------------------------------------

    private function tickWaiting(): void {
        // Natychmiast przejdź do STARTING po pierwszym ticku
        $this->match->setState(MatchState::STARTING);
        ($this->onCountdownTick)($this->countdownLeft);
    }

    private function tickStarting(): void {
        $this->countdownLeft--;

        if ($this->countdownLeft <= 0) {
            // Odliczanie skończone – start meczu
            $this->match->start();
            ($this->onMatchStart)();
        } else {
            ($this->onCountdownTick)($this->countdownLeft);
        }
    }

    private function tickActive(): void {
        // Grace period – PvP zablokowane na początku
        if ($this->gracePeriodLeft > 0) {
            $this->gracePeriodLeft--;
            if ($this->gracePeriodLeft === 0) {
                ($this->onGracePeriodEnd)();
            }
            return;
        }

        $this->activeSecondsElapsed++;

        // Timeout – czas meczu upłynął
        if ($this->activeSecondsElapsed >= $this->matchDurationSeconds) {
            $this->finished = true;
            ($this->onTimeout)();
        }
    }

    // -----------------------------------------------------------------------
    // Odczyt stanu
    // -----------------------------------------------------------------------

    /**
     * Czy PvP jest aktywne (mecz trwa I grace period minął).
     */
    public function isPvpEnabled(): bool {
        return $this->match->getState() === MatchState::ACTIVE
            && $this->gracePeriodLeft <= 0;
    }

    /**
     * Ile sekund zostało do końca meczu (od startu PvP).
     */
    public function getRemainingSeconds(): int {
        return max(0, $this->matchDurationSeconds - $this->activeSecondsElapsed);
    }

    public function getCountdownLeft(): int {
        return $this->countdownLeft;
    }

    public function getGracePeriodLeft(): int {
        return $this->gracePeriodLeft;
    }

    public function isFinished(): bool {
        return $this->finished;
    }

    /**
     * Wymuszony koniec lifecycle (np. gracz wyszedł, mecz zakończony przez MatchManager).
     */
    public function forceEnd(): void {
        $this->finished = true;
    }
}