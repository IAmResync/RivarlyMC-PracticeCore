<?php

declare(strict_types=1);

namespace Application\Tournament;

use pocketmine\Server;

/**
 * TODO: System planowania automatycznych startów turniejów w określone dni i godziny.
 * Działa w tle (cron-like) bez konieczności interwencji ze strony administratora.
 * Zarządza komunikatami o nadchodzących wydarzeniach na czacie serwera.
 */
class TournamentScheduler {

    private TournamentManager $tournamentManager;

    /** @var array<int,string[]> Harmonogram turniejów: [Dzień Tygodnia => ["HH:MM", "HH:MM"]] */
    private array $schedule = [];

    // Zapobiegajmy wielokrotnemu uruchomieniu w ciągu tej samej minuty
    private ?string $lastTriggeredMinute = null;

    public function __construct(TournamentManager $tournamentManager) {
        $this->tournamentManager = $tournamentManager;

        // Przygładowy harmonogram automatyczny:
        // 5 = Piątek, 6 = Sobota, 7 = Niedziela
        $this->schedule[5] =["18:00", "21:00"];
        $this->schedule[6] = ["15:00", "19:00"];
        $this->schedule[7] = ["16:00", "20:00"];
    }

    /**
     * Głowna metoda sprawdzająca i odpowiadająca, Musi być wywoływana co sekcję czasu (np. co 20 sekund / 400 ticków)
     * przez Task zajerestrowany w głównym pluginie.
     */
    public function checkSchedule(): void
    {
        $currentDay = (int)date("N"); // 1 (Poniedziałek) do 7 (Niedziela)
        $currentTime = date("H:i"); // Format "Godzina:Minuta", np. "18:00"

        // Jeśli ta minuta została obsłużona, nic nie robimy.
        if ($this->lastTriggeredMinute === $currentTime) {
            return;
        }

        // Sprawdzamy, czy na dzisiejszy dzień zaplanowano jakieś turnieje.
        if (isset($this->schedule[$currentDay])) {
            foreach ($this->schedule[$currentDay] as $scheduledTime) {

                // 1. Czas na komunikaty o zbliżającym się turnieju (np. 15 minut przed startem)
                $annoucementTime = date("H:i", strtotime($scheduledTime . " -15 minutes"));
                if ($currentTime === $annoucementTime && $this->tournamentManager->getStatus() === "IDLE") {
                    Server::getInstance()->broadcastMessage("[Automatyczny Turniej] Rejestracja rusza o godzinie {$scheduledTime}! Przygotujcie się");
                    $this->lastTriggeredMinute = $currentTime;
                    return;
                }

                // 2. Czas na start rejestracji (dokładna godzina z harmonogramu)
                if ($currentTime === $scheduledTime && $this->tournamentManager->getStatus() === "IDLE") {
                    $this->tournamentManager->openRegistration();
                    $this->lastTriggeredMinute = $currentTime;

                    // Serwer Automatycznie zamknie rejestracje i wystartuje walki za 5 minut.
                    // (To można obsłużyć wewnętrznym opóżnionnym zadaniem lub w checkSchedule)
                    return;
                }
            }
        }
    }

        /**
         * Pozwala na dynamiczne dodanie nowej godziny turnieju do harmonogramu z poziomu kodu/komendy.
         */
        public function addScheduleEvent(int $dayOfWeek, string $time): void {
            $this->schedule[$dayOfWeek][] = $time;
        }

        /**
         * Zwraca aktualny harmonogram automatyczny turniejów
         */
        public function getSchedule(): array {
            return $this->schedule;
        }
}
