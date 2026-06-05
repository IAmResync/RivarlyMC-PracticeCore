<?php

declare(strict_types=1);

namespace Application\Tournament;

use pocketmine\player\Player;

/**
 * TODO: Nadzoruje przebieg aktywnego turnieju od momentu jego zaplanowania.
 * Koordynuje działania managera drabinki i systemu powiadomień turniejowych.
 * Odpowiada za nadawanie nagród i czyszczenie danych po finale turnieju.
 */
class TournamentManager {

    private string $status = "IDLE";
    /** @var array<string, Player> Lista zapisanych uczestników (klucz: UUID) */

    private array $participants = [];
    private int $currentRound = 0;

    public function __construct() {

    }

    /**
     * Rozpoczyna proces rejestracji do nowego turnieju i wysyła powiadomienie na serwer
     */
    public function openRegistration(): void {
        $this->status = "REGISTERING";
        $this->participants = [];
        $this->currentRound = 0;

        $this->broadcastMessage("");
    }

    /**
     * Zapisuje gracza do turnieju.
     */
    public function registerPlayer(Player $player): bool {
        if ($this->status !== "REGISTERING") {
            $player->sendMessage("Rejestracja na turniej nie jest w tym momencie otwarta.");
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        if (isset($this->participants[$uuid])) {
            $player->sendMessage("Jesteś już zapisany na ten turniej.");
            return false;
        }

        $this->participants[$uuid] = $player;
        $player->sendMessage("Pomyślnie zapisano na turniej.");
        return true;
    }

    /**
     * Startuje turniej, zamyka zapisy i inicjuje pierwszą rundę,
     */
    public function startTournament(): void {
        if ($this->status !== "REGISTERING") {
            return;
        }

        if (count($this->participants) < 2) {
            $this->broadcastMessage("Turniej odwołany - zbyt mała liczba uczestników (wymagane minimum 2 osoby).");
            $this->status = "IDLE";
            return;
        }

        $this->status = "ACTIVE";
        $this->currentRound = 1;

        $this->broadcastMessage("TURNIEJ WYSTARTOWAŁ! Przygotowywanie drabinki walk dla Rundy {$this->currentRound}...");

        // Tutaj w przyszłości wejdzie interakcja z BracketGenerator:
    }

    /**
     * Kończy turniej, wyłania zwycięzcę, przyznaje nagrody i czyści pamieć RAM.
     */
    public function endTournament(Player $winner): void {
        if ($this->status !== "ACTIVE") {
            return;
        }

        // Ogłoszenie sukcesu na całym serwerze
        $this->broadcastMessage("TURNIEJ ZAKOŃCZONY! Wielkim zwycięzcą zostaje gracz:" . $winner->getName() . "!");

        // Nadawanie nagród zwycięzcy (przykładowe dodanie itemu do ekwipunku)
        // Możesz to rozbudować o integrację z systemem ekonomii lub monet.
        $winner->sendMessage("Otrzymujesz nagrodę główną za wygranie turnieju!");

        // Czyszczenie danych po finale turnieju, aby zwolnić pamieć RAM
        $this->cleanupTournamentData();
    }

    /**
     * Czyści strukturę danych turnieju, przywracając stan początkowy.
     */
    public function cleanupTournamentData(): void {
        $this->participants = [];
        $this->currentRound = 0;
        $this->status = "IDLE";
    }

    /**
     * Zwraca aktualny status turnieju.
     */
    public function getStatus(): string {
        return $this->status;
    }

    /**
     * Zwraca aktualną rundę turnieju.
     */
    public function getCurrentRound(): int {
        return $this->currentRound;
    }

    /**
     * Zwraca listę wszystkich zarejestrowanych graczy.
     * @return array<string, Player>
     */
    public function getParticipants(): array {
        return $this->participants;
    }
}
