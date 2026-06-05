<?php

declare(strict_types=1);

namespace Domain\Ranking;

/**
 * Archiwalna migawka rankingu na koniec danego sezonu.
 * W pełni zautomatyzowana i przystosowana do zapisu i odczytu z bazy danych NoMercy.
 */
final class SeasonSnapshot {

    private int $seasonId;
    private int $endedAt;
    private int $totalPlayers;

    /** @var list<SeasonPlayerEntry> Topka graczy w tym sezonie */
    private array $leaderboard;

    /**
     * @param list<SeasonPlayerEntry> $leaderboard
     */
    public function __construct(
        int $seasonId,
        int $endedAt,
        int $totalPlayers,
        array $leaderboard
    ) {
        $this->seasonId = $seasonId;
        $this->endedAt = $endedAt;
        $this->totalPlayers = $totalPlayers;
        $this->leaderboard = $leaderboard;
    }

    // -----------------------------------------------------------------------
    // Klasyczne, bezpieczne gettery NoMercy
    // -----------------------------------------------------------------------

    public function getSeasonId(): int {
        return $this->seasonId;
    }

    public function getEndedAt(): int {
        return $this->endedAt;
    }

    public function getTotalPlayers(): int {
        return $this->totalPlayers;
    }

    /**
     * @return list<SeasonPlayerEntry>
     */
    public function getLeaderboard(): array {
        return $this->leaderboard;
    }

    /**
     * Zwraca pozycję gracza w rankingu (np. 1 dla pierwszego miejsca).
     * Przydatne do sprawdzania, czy graczowi należy się nagroda za TOP 1/3/10!
     * Zwraca null, jeśli gracz nie znalazł się w zapisanej migawce.
     */
    public function getPlayerPosition(string $playerUuid): ?int {
        foreach ($this->leaderboard as $index => $entry) {
            if ($entry->getPlayerUuid() === $playerUuid) {
                return $index + 1;
            }
        }
        return null;
    }

    /**
     * Zwraca pełny archiwalny wpis gracza z tej migawki.
     */
    public function getPlayerEntry(string $playerUuid): ?SeasonPlayerEntry {
        foreach ($this->leaderboard as $entry) {
            if ($entry->getPlayerUuid() === $playerUuid) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Zwraca wycinek topki (np. tylko TOP 3 do wiadomości powitalnej lub hologramu).
     * @return list<SeasonPlayerEntry>
     */
    public function getTopPlayers(int $limit): array {
        return array_slice($this->leaderboard, 0, $limit);
    }

    // -----------------------------------------------------------------------
    // Głęboka serializacja i odczyt danych (Zapis i Odczyt z SQL / JSON)
    // -----------------------------------------------------------------------

    /**
     * Serializuje całą migawkę sezonu wraz z całą listą topki do tablicy.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'season_id'     => $this->seasonId,
            'ended_at'      => $this->endedAt,
            'total_players' => $this->totalPlayers,
            'leaderboard'   => array_map(fn(SeasonPlayerEntry $entry) => $entry->toArray(), $this->leaderboard)
        ];
    }

    /**
     * Rekonstruuje pełną migawkę sezonu wraz z obiektami wpisów graczy z bazy danych.
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $rawLeaderboard = $data['leaderboard'] ?? [];
        if (is_string($rawLeaderboard)) {
            $rawLeaderboard = json_decode($rawLeaderboard, true) ?? [];
        }

        $leaderboard = [];
        foreach ($rawLeaderboard as $entryData) {
            if (is_array($entryData)) {
                $leaderboard[] = SeasonPlayerEntry::fromArray($entryData);
            }
        }

        return new self(
            seasonId: (int) ($data['season_id'] ?? 1),
            endedAt: (int) ($data['ended_at'] ?? time()),
            totalPlayers: (int) ($data['total_players'] ?? count($leaderboard)),
            leaderboard: $leaderboard
        );
    }
}