<?php

declare(strict_types=1);

namespace Domain\Ranking;

use Domain\Player\Division;

/**
 * Reprezentuje archiwalny wpis gracza w rankingu konkretnego sezonu.
 * Autorska implementacja NoMercy wzbogacona o obsługę bazy danych i JSON.
 */
final class SeasonPlayerEntry {

    private string $playerUuid;
    private string $playerName;
    private int $elo;
    private Division $division;
    private int $wins;
    private int $losses;
    private int $matchesPlayed;
    private float $winRate;

    public function __construct(
        string $playerUuid,
        string $playerName,
        int $elo,
        Division $division,
        int $wins,
        int $losses,
        int $matchesPlayed
    ) {
        $this->playerUuid = $playerUuid;
        $this->playerName = $playerName;
        $this->elo = $elo;
        $this->division = $division;
        $this->wins = $wins;
        $this->losses = $losses;
        $this->matchesPlayed = $matchesPlayed;
        $this->winRate = $matchesPlayed > 0
            ? round(($wins / $matchesPlayed) * 100, 2)
            : 0.0;
    }

    // -----------------------------------------------------------------------
    // Bezpieczne gettery w stylu NoMercy
    // -----------------------------------------------------------------------

    public function getPlayerUuid(): string { return $this->playerUuid; }
    public function getPlayerName(): string { return $this->playerName; }
    public function getElo(): int { return $this->elo; }

    /**
     * Zwraca kolorową, sformatowaną nazwę dywizji jako czysty string (bezpieczeństwo dla Scoreboardu/czatu).
     */
    public function getDivisionName(): string { return $this->division->getDisplayName(); }

    /**
     * Zwraca surowy obiekt Enuma dywizji.
     */
    public function getDivision(): Division { return $this->division; }

    public function getWins(): int { return $this->wins; }
    public function getLosses(): int { return $this->losses; }
    public function getMatchesPlayed(): int { return $this->matchesPlayed; }
    public function getWinRate(): float { return $this->winRate; }

    // -----------------------------------------------------------------------
    // Serializacja danych do bazy danych (Zapis i Odczyt JSON)
    // -----------------------------------------------------------------------

    /**
     * Konwertuje wpis sezonowy do tablicy przed zapisem do bazy.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'player_uuid'    => $this->playerUuid,
            'player_name'    => $this->playerName,
            'elo'            => $this->elo,
            'division'       => $this->division->value, // Zapisujemy string enuma (np. 'bronze_i')
            'wins'           => $this->wins,
            'losses'         => $this->losses,
            'matches_played' => $this->matchesPlayed,
        ];
    }

    /**
     * Odtwarza historyczny wpis sezonowy bezpośrednio z bazy danych.
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $elo = (int) ($data['elo'] ?? 1000);

        // Bezpieczne odtworzenie enuma dywizji na podstawie zapisanego stringa lub punktów ELO
        $divisionValue = (string) ($data['division'] ?? '');
        $division = Division::tryFrom($divisionValue) ?? Division::fromElo($elo);

        return new self(
            playerUuid: (string) ($data['player_uuid'] ?? ''),
            playerName: (string) ($data['player_name'] ?? 'Unknown'),
            elo: $elo,
            division: $division,
            wins: (int) ($data['wins'] ?? 0),
            losses: (int) ($data['losses'] ?? 0),
            matchesPlayed: (int) ($data['matches_played'] ?? 0)
        );
    }
}