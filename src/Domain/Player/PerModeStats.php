<?php

declare(strict_types=1);

namespace Domain\Player;

/**
 * Statystyki gracza w jednym konkretnym trybie gry (np. Sumo, Nodebuff).
 * Autorska implementacja zsynchronizowana z profilem NoMercy.
 */
final class PerModeStats {

    private string $gameMode;
    private int $elo;
    private int $wins = 0;
    private int $losses = 0;
    private int $kills = 0;
    private int $deaths = 0;
    private int $matchesPlayed = 0;
    private int $bestWinStreak = 0;
    private int $currentWinStreak = 0;

    public function __construct(string $gameMode, int $elo = 1000) {
        $this->gameMode = $gameMode;
        $this->elo = $elo;
    }

    public function recordWin(): void {
        $this->wins++;
        $this->matchesPlayed++;
        $this->currentWinStreak++;
        if ($this->currentWinStreak > $this->bestWinStreak) {
            $this->bestWinStreak = $this->currentWinStreak;
        }
    }

    public function recordLoss(): void {
        $this->losses++;
        $this->matchesPlayed++;
        $this->currentWinStreak = 0;
    }

    public function recordKill(): void {
        $this->kills++;
    }

    public function recordDeath(): void {
        $this->deaths++;
    }

    public function applyEloDelta(int $delta): void {
        $this->elo = max(0, $this->elo + $delta);
    }

    public function getWinRate(): float {
        if ($this->matchesPlayed === 0) return 0.0;
        return round(($this->wins / $this->matchesPlayed) * 100, 2);
    }

    public function getKdr(): float {
        if ($this->deaths === 0) return (float) $this->kills;
        return round($this->kills / $this->deaths, 2);
    }

    // -----------------------------------------------------------------------
    // Gettery i Settery pod Twoje systemy topek i komend
    // -----------------------------------------------------------------------

    public function getGameMode(): string         { return $this->gameMode; }
    public function getElo(): int                 { return $this->elo; }
    public function setElo(int $elo): void        { $this->elo = max(0, $elo); }
    public function getWins(): int                { return $this->wins; }
    public function getLosses(): int              { return $this->losses; }
    public function getKills(): int               { return $this->kills; }
    public function getDeaths(): int              { return $this->deaths; }
    public function getMatchesPlayed(): int       { return $this->matchesPlayed; }
    public function getBestWinStreak(): int       { return $this->bestWinStreak; }
    public function getCurrentWinStreak(): int    { return $this->currentWinStreak; }

    /**
     * Serializacja danych pojedynczego trybu do bazy danych.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'elo'                => $this->elo,
            'wins'               => $this->wins,
            'losses'             => $this->losses,
            'kills'              => $this->kills,
            'deaths'             => $this->deaths,
            'matches_played'     => $this->matchesPlayed,
            'best_win_streak'    => $this->bestWinStreak,
            'current_win_streak' => $this->currentWinStreak,
        ];
    }

    /**
     * Odtworzenie statystyk trybu z rekordu bazy danych.
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $gameMode, array $data): self {
        $stats = new self($gameMode, (int) ($data['elo'] ?? 1000));
        $stats->wins              = (int) ($data['wins'] ?? 0);
        $stats->losses            = (int) ($data['losses'] ?? 0);
        $stats->kills             = (int) ($data['kills'] ?? 0);
        $stats->deaths            = (int) ($data['deaths'] ?? 0);
        $stats->matchesPlayed     = (int) ($data['matches_played'] ?? 0);
        $stats->bestWinStreak     = (int) ($data['best_win_streak'] ?? 0);
        $stats->currentWinStreak  = (int) ($data['current_win_streak'] ?? 0);
        return $stats;
    }
}