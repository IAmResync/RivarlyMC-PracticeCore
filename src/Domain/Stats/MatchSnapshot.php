<?php

declare(strict_types=1);

namespace Domain\Stats;

/**
 * Niezmienny (immutable) zapis statystyk gracza z pojedynczego meczu.
 * Służy do zapisu w logach bazy danych, wyświetlania podsumowań w GUI oraz na stronie WWW.
 */
final class MatchSnapshot {

    private string $playerUuid;
    private string $playerName;
    private int $swings;
    private int $hits;
    private int $missedHits;
    private int $criticalHits;
    private int $potionsUsed;
    private int $goldenApplesEaten;
    private int $enderPearlsThrown;
    private int $damageDealt;
    private int $damageTaken;
    private int $longestCombo;
    private int $kills;
    private int $deaths;
    private float $accuracy;

    public function __construct(
        string $playerUuid,
        string $playerName,
        int $swings,
        int $hits,
        int $missedHits,
        int $criticalHits,
        int $potionsUsed,
        int $goldenApplesEaten,
        int $enderPearlsThrown,
        int $damageDealt,
        int $damageTaken,
        int $longestCombo,
        int $kills,
        int $deaths,
        float $accuracy
    ) {
        $this->playerUuid = $playerUuid;
        $this->playerName = $playerName;
        $this->swings = $swings;
        $this->hits = $hits;
        $this->missedHits = $missedHits;
        $this->criticalHits = $criticalHits;
        $this->potionsUsed = $potionsUsed;
        $this->goldenApplesEaten = $goldenApplesEaten;
        $this->enderPearlsThrown = $enderPearlsThrown;
        $this->damageDealt = $damageDealt;
        $this->damageTaken = $damageTaken;
        $this->longestCombo = $longestCombo;
        $this->kills = $kills;
        $this->deaths = $deaths;
        $this->accuracy = $swings > 0 ? min(100.0, round($accuracy, 2)) : 0.0;
    }

    // -----------------------------------------------------------------------
    // Bezpieczne gettery NoMercy
    // -----------------------------------------------------------------------

    public function getPlayerUuid(): string { return $this->playerUuid; }
    public function getPlayerName(): string { return $this->playerName; }
    public function getSwings(): int { return $this->swings; }
    public function getHits(): int { return $this->hits; }
    public function getMissedHits(): int { return $this->missedHits; }
    public function getCriticalHits(): int { return $this->criticalHits; }
    public function getPotionsUsed(): int { return $this->potionsUsed; }
    public function getGoldenApplesEaten(): int { return $this->goldenApplesEaten; }
    public function getEnderPearlsThrown(): int { return $this->enderPearlsThrown; }
    public function getDamageDealt(): int { return $this->damageDealt; }
    public function getDamageTaken(): int { return $this->damageTaken; }
    public function getLongestCombo(): int { return $this->longestCombo; }
    public function getKills(): int { return $this->kills; }
    public function getDeaths(): int { return $this->deaths; }
    public function getAccuracy(): float { return $this->accuracy; }

    // -----------------------------------------------------------------------
    // Serializacja i Rekonstrukcja danych (Zapis i Odczyt z bazy/JSON)
    // -----------------------------------------------------------------------

    /**
     * Zwraca dane snapshotu jako czystą tablicę gotową do zapisu lub wysłania przez API.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'player_uuid'         => $this->playerUuid,
            'player_name'         => $this->playerName,
            'swings'              => $this->swings,
            'hits'                => $this->hits,
            'missed_hits'         => $this->missedHits,
            'critical_hits'       => $this->criticalHits,
            'potions_used'        => $this->potionsUsed,
            'golden_apples_eaten' => $this->goldenApplesEaten,
            'ender_pearls_thrown' => $this->enderPearlsThrown,
            'damage_dealt'        => $this->damageDealt,
            'damage_taken'        => $this->damageTaken,
            'longest_combo'       => $this->longestCombo,
            'kills'               => $this->kills,
            'deaths'              => $this->deaths,
            'accuracy'            => $this->accuracy,
        ];
    }

    /**
     * Odtwarza snapshot z danych tablicowych pobranych z bazy danych NoMercy.
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            playerUuid: (string) ($data['player_uuid'] ?? ''),
            playerName: (string) ($data['player_name'] ?? 'Unknown'),
            swings: (int) ($data['swings'] ?? 0),
            hits: (int) ($data['hits'] ?? 0),
            missedHits: (int) ($data['missed_hits'] ?? 0),
            criticalHits: (int) ($data['critical_hits'] ?? 0),
            potionsUsed: (int) ($data['potions_used'] ?? 0),
            goldenApplesEaten: (int) ($data['golden_apples_eaten'] ?? 0),
            enderPearlsThrown: (int) ($data['ender_pearls_thrown'] ?? 0),
            damageDealt: (int) ($data['damage_dealt'] ?? 0),
            damageTaken: (int) ($data['damage_taken'] ?? 0),
            longestCombo: (int) ($data['longest_combo'] ?? 0),
            kills: (int) ($data['kills'] ?? 0),
            deaths: (int) ($data['deaths'] ?? 0),
            accuracy: (float) ($data['accuracy'] ?? 0.0)
        );
    }
}