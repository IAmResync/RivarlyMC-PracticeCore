<?php

declare(strict_types=1);

namespace Application\Season;

use pocketmine\Server;

/**
 * TODO: Zarządza aktywnym sezonem: tworzy nowy, sprawdza stan i ogłasza finał.
 * Komunikuje graczom zbliżający się koniec sezonu (tryb ENDING przez 7 dni).
 * Deleguje reset ELO i nagrody do SeasonResetService po zakończeniu sezonu.
 */
class SeasonManager {

    private string $currentSeasonName = "Season 1";
    private string $status = "ACTIVE"; // ACTIVE, ENDING, FINISHED
    private int $endTimestamp;

    private SeasonResetService $seasonResetService;

    public function __construct(SeasonResetService $seasonResetService) {
        $this->seasonResetService = $seasonResetService;

        // Domyślnie ustawiamy koniec sezonu np. 30 dni od teraz (w celach testowych)
        $this->endTimestamp = time() + (30 * 24 * 60 * 60);
    }

    /**
     * Główna metoda monitorująca czas (wywoływana co jakiś czas przez Scheduler serwera).
     */
    public function checkSeasonProgress(): void {
        $timeleft = $this->endTimestamp - time();

        // 1. Jeśli do końca zostało mniej niż 7 dni, a status to wciąż ACTIVE -> przełączamy na ENDING
        if ($timeleft <= (7 * 24 * 60 * 60) && $this->status === "ACTIVE") {
            $this->status = "ENDING";
            $this->broadcastMessage("[RivarlyMC] Zbliża się koniec sezonu '{$this->currentSeasonName}'! Pozostało mniej niż 7 dni na wbicie ELO!");
        }

        // 2. Jeśli czas minął, a sezon jeszcze się nie zakończył -> odpalamy finał!
        if ($timeleft <= 0 && (7 * 24 * 60 * 60) && $this->status !== "FINISHED") {
            $this->endCurrentSeason();
        }
    }

        /**
         * Tworzy i konfiguruje zupełnie nowy sezon.
         */
        public function startNewSeason(string $name, int $durationDays): void {
            $this->currentSeasonName = $name;
            $this->status = "ACTIVE";
            $this->endTimestamp = time() + ($durationDays * 24 * 60 * 60);

            $this->broadcastMessage("[RivarlyMC] Wystartował zupełnie nowy: {$this->currentSeasonName}! Powodzenia w walce o topkę!");
        }

        /**
         * Kończy obecny sezon, ogłasza zwycięzców i wywołuje asynchroniczny reset bazy.
         */
        public function endCurrentSeason(): void {
            $this->status = "FINISHED";

            $this->broadcastMessage("[RivarlyMC] Sezon '{$this->currentSeasonName}' dobiegł końca! Trwa podliczanie wyników i przyznawanie nagród...");

            // Delegujemy reset parametrów oraz nagrody bezpośrednio do SeasonResetService
            $this->seasonResetService->executeSeasonReset($this->currentSeasonName);
        }

        /**
         * Pomocniczy broadcast wiadomości na cały serwer.
         */
        public function broadcastMessage(string $message): void {
            Server::getInstance()->broadcastMessage($message);
        }

        /**
         * Zwraca nazwę aktualnego sezonu.
         */
        public function getCurrentSeasonName(): string {
            return $this->currentSeasonName;
        }

        /**
         * Zwraca aktualny status (ACTIVE, ENDING, FINISHED).
         */
        public function getStatus(): string {
            return $this->status;
        }

        /**
         * Zwraca sformatowany czas do końca sezonu.
         */
        public function getTimeLeftString(): string {
            $timeLeft = $this->endTimestamp - time();

            if ($timeLeft <= 0) {
                return "END";
            }

            $days = floor($timeLeft / (24 * 60 * 60));
            $hours = floor(($timeLeft % (24 * 60 * 60)) / (60 * 60));

            return "{$days}d {$hours}h";
        }
}
