<?php

declare(strict_types=1);

namespace Domain\Match;

/**
 * Definiuje stany i etapy cyklu życia meczu na serwerze.
 * Autorska implementacja z metodami ułatwiającymi kontrolę eventów w grze.
 */
enum MatchState: string {

    case WAITING  = 'waiting';  // Oczekiwanie na graczy / ładowanie areny
    case STARTING = 'starting'; // Odliczanie do startu (np. 5 sekund freeze-time)
    case ACTIVE   = 'active';   // Trwa walka PvP
    case ENDING   = 'ending';   // Walka zakończona, trwa sprzątanie areny i rozdawanie ELO

    /**
     * Czy walka PvP jest aktywna (gracze mogą się bić i zadawać obrażenia).
     */
    public function isCombatActive(): bool {
        return $this === self::ACTIVE;
    }

    /**
     * Czy mecz jest oficjalnie zakończony.
     */
    public function isOver(): bool {
        return $this === self::ENDING;
    }

    /**
     * Czy mecz jest w fazie przygotowawczej (przed startem PvP).
     */
    public function isPreMatch(): bool {
        return $this === self::WAITING || $this === self::STARTING;
    }

    /**
     * Czy gracze na arenie powinni mieć zablokowaną możliwość poruszania się i interakcji.
     * Przydatne do wpięcia w PlayerMoveEvent, EntityDamageEvent oraz PlayerInteractEvent.
     */
    public function shouldFreeze(): bool {
        return $this === self::WAITING || $this === self::STARTING;
    }
}