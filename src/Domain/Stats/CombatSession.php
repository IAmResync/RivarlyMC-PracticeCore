<?php

declare(strict_types=1);

namespace Domain\Stats;

/**
 * Aktywna sesja zbierania statystyk bojowych gracza podczas trwania meczu.
 * Żyje wyłącznie w pamięci RAM i działa jako "czarna skrzynka" pojedynku.
 */
final class CombatSession {

    private string $playerUuid;
    private string $playerName;
    private AccuracyTracker $accuracyTracker;

    private int $criticalHits;
    private int $potionsUsed;
    private int $goldenApplesEaten;
    private int $enderPearlsThrown;
    private int $damageDealt;
    private int $damageTaken;
    private int $longestCombo;
    private int $currentCombo;
    private int $kills;
    private int $deaths;

    public function __construct(string $playerUuid, string $playerName) {
        $this->playerUuid = $playerUuid;
        $this->playerName = $playerName;
        $this->accuracyTracker = new AccuracyTracker();
        $this->criticalHits = 0;
        $this->potionsUsed = 0;
        $this->goldenApplesEaten = 0;
        $this->enderPearlsThrown = 0;
        $this->damageDealt = 0;
        $this->damageTaken = 0;
        $this->longestCombo = 0;
        $this->currentCombo = 0;
        $this->kills = 0;
        $this->deaths = 0;
    }

    // -----------------------------------------------------------------------
    // Gettery NoMercy
    // -----------------------------------------------------------------------

    public function getPlayerUuid(): string { return $this->playerUuid; }
    public function getPlayerName(): string { return $this->playerName; }
    public function getCriticalHits(): int { return $this->criticalHits; }
    public function getPotionsUsed(): int { return $this->potionsUsed; }
    public function getGoldenApplesEaten(): int { return $this->goldenApplesEaten; }
    public function getEnderPearlsThrown(): int { return $this->enderPearlsThrown; }
    public function getDamageDealt(): int { return $this->damageDealt; }
    public function getDamageTaken(): int { return $this->damageTaken; }
    public function getLongestCombo(): int { return $this->longestCombo; }
    public function getCurrentCombo(): int { return $this->currentCombo; }
    public function getKills(): int { return $this->kills; }
    public function getDeaths(): int { return $this->deaths; }

    // -----------------------------------------------------------------------
    // Delegowanie statystyk celności (AccuracyTracker)
    // -----------------------------------------------------------------------

    public function getSwings(): int { return $this->accuracyTracker->getSwings(); }
    public function getHits(): int { return $this->accuracyTracker->getHits(); }
    public function getMissedHits(): int { return $this->accuracyTracker->getMissed(); }
    public function getAccuracy(): float { return $this->accuracyTracker->getAccuracy(); }
    public function getAccuracyTracker(): AccuracyTracker { return $this->accuracyTracker; }

    // -----------------------------------------------------------------------
    // Rejestracja zdarzeń w locie (Real-time tracking)
    // -----------------------------------------------------------------------

    /**
     * Rejestruje samo machnięcie ręką/mieczem.
     */
    public function recordSwing(): void {
        $this->accuracyTracker->recordSwing();
    }

    /**
     * Rejestruje czyste trafienie w przeciwnika i przelicza combo.
     */
    public function recordHit(bool $critical = false): void {
        $this->accuracyTracker->recordHit();
        $this->currentCombo++;

        if ($this->currentCombo > $this->longestCombo) {
            $this->longestCombo = $this->currentCombo;
        }
        if ($critical) {
            $this->criticalHits++;
        }
    }

    /**
     * Rejestruje chybienie (pudło). Resetuje combo tylko, jeśli faktycznie uderzenie minęło cel.
     */
    public function recordMiss(): void {
        $this->currentCombo = 0;
    }

    public function recordPotionUsed(): void {
        $this->potionsUsed++;
    }

    public function recordGoldenAppleEaten(): void {
        $this->goldenApplesEaten++;
    }

    public function recordEnderPearlThrown(): void {
        $this->enderPearlsThrown++;
    }

    public function recordDamageDealt(int $damage): void {
        $this->damageDealt += $damage;
    }

    /**
     * Rejestruje obrażenia otrzymane od wroga.
     * Na serwerze Practice otrzymanie hitu automatycznie przerywa Twoje combo!
     */
    public function recordDamageTaken(int $damage): void {
        $this->damageTaken += $damage;
        $this->currentCombo = 0;
    }

    public function recordKill(): void {
        $this->kills++;
    }

    public function recordDeath(): void {
        $this->deaths++;
        $this->currentCombo = 0;
    }

    // -----------------------------------------------------------------------
    // Konwersja na gotowy Snapshot historyczny
    // -----------------------------------------------------------------------

    /**
     * Zamraża stan sesji i przekształca ją w niezmienny (immutable) Snapshot do bazy danych.
     */
    public function toSnapshot(): MatchSnapshot {
        return new MatchSnapshot(
            playerUuid: $this->playerUuid,
            playerName: $this->playerName,
            swings: $this->accuracyTracker->getSwings(),
            hits: $this->accuracyTracker->getHits(),
            missedHits: $this->accuracyTracker->getMissed(),
            criticalHits: $this->criticalHits,
            potionsUsed: $this->potionsUsed,
            goldenApplesEaten: $this->goldenApplesEaten,
            enderPearlsThrown: $this->enderPearlsThrown,
            damageDealt: $this->damageDealt,
            damageTaken: $this->damageTaken,
            longestCombo: $this->longestCombo,
            kills: $this->kills,
            deaths: $this->deaths,
            accuracy: $this->accuracyTracker->getAccuracy()
        );
    }
}