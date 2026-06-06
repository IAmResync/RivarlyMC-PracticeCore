<?php

declare(strict_types=1);

namespace Domain\Tournament;

/**
 * TODO: Główny model zarządzający strukturą i uczestnikami turnieju.
 * Odpowiada za przyjmowanie zapisów i monitorowanie postępów graczy w drabince.
 * Kontroluje harmonogram meczów turniejowych od ćwierćfinałów po finał.
 */
class Tournament {

    private string $id;
    private string $name;
    private string $gameModeName;
    private TournamentState $tournamentState;
    private array $participants = [];

    private int $maxParticipants;
    private \DateTimeImmutable $scheduledStartTime;
    /** @var array<string, string> array{player_name: player_name}*/

    /**
     * Konstruktor modelu turnieju
     */

    public function __construct(
        string $id,
        string $name,
        string $gameModeName,
        \DateTimeImmutable $scheduledStartTime,
        int $maxParticipants = 16
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->gameModeName = $gameModeName;
        $this->scheduledStartTime = $scheduledStartTime;
        $this->maxParticipants = $maxParticipants;
        $this->tournamentState = TournamentState::SCHEDULED;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getGameModeName(): string {
        return $this->gameModeName;
    }

    public function getTournamentState(): TournamentState {
        return $this->tournamentState;
    }

    /**
     * Zwraca listę zajerestrowanych graczy (nazwy pisane małymi literami jako klucze dla optymalizacji).
     *
     * @return array<string, string>
     */
    public function getParticipants(): array {
        return $this->participants;
    }

    public function getScheduledStartTime(): \DateTimeImmutable {
        return $this->scheduledStartTime;
    }

    public function getMaxParticipants(): int {
        return $this->maxParticipants;
    }

    /**
     * Logika dołączenia do turnieju.
     */
    public function registerPlayer(string $playerName): bool {
        if ($this->tournamentState !== TournamentState::SCHEDULED) {
            return false;
        }

        if (count($this->participants) >= $this->maxParticipants) {
            return false;
        }

        $lowerName = strtolower($playerName);
        if (isset($this->participants[$lowerName])) {
            return false;
        }
        $this->participants[$lowerName] = $playerName;
        return true;
    }

    /**
     * Logika opuszczania zapisów turnieju.
     */
    public function unregisterPlayer(string $playerName): bool {
        if ($this->tournamentState !== TournamentState::SCHEDULED) {
            return false;
        }

        $lowerName = strtolower($playerName);
        if (!isset($this->participants[$lowerName])) {
            return false;
        }

        unset($this->participants[$lowerName]);
        return true;
    }

    /**
     * Otwiera zapisy do turnieju.
     */
    public function openRegistration(): void {
        if ($this->tournamentState === TournamentState::SCHEDULED) {
            $this->tournamentState = TournamentState::REGISTRATION;
        }
    }

    /**
     * Uruchamia strukturę drabinki i blokuje możliwość zapisów
     */
    public function startTournament(): bool {
        if ($this->tournamentState !== TournamentState::REGISTRATION) {
            return false;
        }

        // Do startu turnieju wymagana jest parzysta liczba graczy i minimum np. 2 osoby.
        if (count($this->participants) < 2) {
            return false;
        }
        $this->tournamentState = TournamentState::ACTIVE;
        return true;
    }

    /**
     * Kończy turniej i zamyka jego instancję.
     */
    public function finishTournament(): void {
        $this->tournamentState = TournamentState::FINISHED;
    }
}
