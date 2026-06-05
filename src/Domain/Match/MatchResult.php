<?php

declare(strict_types=1);

namespace Domain\Match;

use Domain\Stats\MatchSnapshot;

/**
 * Obiekt przechowujący ostateczne statystyki i końcowy wynik walki.
 * Przystosowany do zapisu w logach bazy danych MySQL/SQLite systemu NoMercy.
 */
final class MatchResult {

    private string $matchId;
    private string $gameMode;
    private string $winnerUuid;
    private string $loserUuid;
    private int $durationSeconds;
    private int $endedAt;

    /** @var array<string, MatchSnapshot> playerUuid => snapshot statystyk z walki */
    private array $playerSnapshots;

    /**
     * @param array<string, MatchSnapshot> $playerSnapshots
     */
    public function __construct(
        string $matchId,
        string $gameMode,
        string $winnerUuid,
        string $loserUuid,
        int $durationSeconds,
        array $playerSnapshots,
        int $endedAt = 0
    ) {
        $this->matchId = $matchId;
        $this->gameMode = $gameMode;
        $this->winnerUuid = $winnerUuid;
        $this->loserUuid = $loserUuid;
        $this->durationSeconds = $durationSeconds;
        $this->playerSnapshots = $playerSnapshots;
        $this->endedAt = $endedAt === 0 ? time() : $endedAt;
    }

    // -----------------------------------------------------------------------
    // Bezpieczne gettery w stylu NoMercy
    // -----------------------------------------------------------------------

    public function getMatchId(): string { return $this->matchId; }
    public function getGameMode(): string { return $this->gameMode; }
    public function getWinnerUuid(): string { return $this->winnerUuid; }
    public function getLoserUuid(): string { return $this->loserUuid; }
    public function getDurationSeconds(): int { return $this->durationSeconds; }
    public function getEndedAt(): int { return $this->endedAt; }

    /**
     * @return array<string, MatchSnapshot>
     */
    public function getPlayerSnapshots(): array {
        return $this->playerSnapshots;
    }

    /**
     * Szybkie wyciągnięcie statystyk zwycięzcy (np. ile potek mu zostało, ile zadał hiciaków).
     */
    public function getWinnerSnapshot(): ?MatchSnapshot {
        return $this->playerSnapshots[$this->winnerUuid] ?? null;
    }

    /**
     * Szybkie wyciągnięcie statystyk przegranego.
     */
    public function getLoserSnapshot(): ?MatchSnapshot {
        return $this->playerSnapshots[$this->loserUuid] ?? null;
    }

    /**
     * Zwraca informację, czy walka zakończyła się szybką dominacją (poniżej minuty).
     */
    public function isDominantWin(): bool {
        return $this->durationSeconds <= 60;
    }

    // -----------------------------------------------------------------------
    // Serializacja i persistence (Zapis i Odczyt z bazy danych)
    // -----------------------------------------------------------------------

    /**
     * Przygotowuje pełny log walki w formie płaskiej tablicy gotowej do zapisu SQL.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        $serializedSnapshots = [];
        foreach ($this->playerSnapshots as $uuid => $snapshot) {
            $serializedSnapshots[$uuid] = $snapshot->toArray();
        }

        return [
            'match_id'         => $this->matchId,
            'game_mode'        => $this->gameMode,
            'winner_uuid'      => $this->winnerUuid,
            'loser_uuid'       => $this->loserUuid,
            'duration_seconds' => $this->durationSeconds,
            'ended_at'         => $this->endedAt,
            'player_snapshots' => json_encode($serializedSnapshots)
        ];
    }

    /**
     * Odtwarza archiwalny log walki prosto z bazy danych.
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $rawSnapshots = $data['player_snapshots'] ?? [];
        if (is_string($rawSnapshots)) {
            $rawSnapshots = json_decode($rawSnapshots, true) ?? [];
        }

        $snapshots = [];
        foreach ($rawSnapshots as $uuid => $snapshotData) {
            if (is_array($snapshotData)) {
                $snapshots[$uuid] = MatchSnapshot::fromArray($snapshotData);
            }
        }

        return new self(
            matchId: (string) $data['match_id'],
            gameMode: (string) $data['game_mode'],
            winnerUuid: (string) $data['winner_uuid'],
            loserUuid: (string) $data['loser_uuid'],
            durationSeconds: (int) $data['duration_seconds'],
            playerSnapshots: $snapshots,
            endedAt: (int) ($data['ended_at'] ?? time())
        );
    }
}