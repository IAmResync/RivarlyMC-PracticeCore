<?php

declare(strict_types=1);

namespace Application\Event;

use pocketmine\Server;

/**
 * TODO: Automatycznie uruchamia zaplanowane competitive eventy bez akcji admina.
 * Czyta harmonogram z config (np. "każdy piątek 20:00 → Nodebuff Cup").
 * Ogłasza nadchodzące eventy graczom na czacie z wyprzedzeniem 30/10/1 minuty.
 */
class EventScheduler {

    private EventManager $eventManager;

    /** @var array<int, array<string, mixed>> Harmonogram z configu [DzieńTygodnia => ["20:00" => ["name" => "...", "mode" => "..."]]] */
    private array $eventSchedule = [];

    // Blokada zapobiegająca powtarzaniu akcji tej samej minucie
    private ?string $lastProcessedMinute = null;

    public function __construct(EventManager $eventManager) {
        $this->eventManager = $eventManager;

        // Przykładowe załadowanie konfiguracji (w przeszłości można tu wpiąć ładowanie z config.yml )
        // 5 = Piątek (zgodnie z formatem date("N"))
        $this->eventSchedule[5] = [
            "20:00" => [
                "id" => "daily_nodebuff",
                "name" => "Daily Nodebuff Cup",
                "mode" => "NoDebuff 1v1",
                "rewards" => [
                    "top1" => "Vip na 14 dni + Unikalny Tytuł",
                    "top2" => "Klucz do skrzyni Eventowej"
                ]
            ]
        ];
    }

    /**
     * Tę metodę należy wywoływać cyklicznie (np. co 20 sekund) z poziomu powtarzalnego zadania (Task) w głównym pluginie.
     */
    public function tick(): void {
        $currentDay = (int) date("N");
        $currentTime = date("H:i");

        // Jeśli ta minuta została już obsłużona, przerywamy sprawdzanie
        if ($this->lastProcessedMinute === $currentTime) {
            return;
        }

        // Sprawdzamy, czy na dzisiaj są zaplanowane jakieś eventy
        if (!isset($this->eventSchedule[$currentDay])) {
            return;
        }

        foreach ($this->eventSchedule[$currentDay] as $scheduledTime => $eventData) {
            $timestampStart = strtotime($scheduledTime);

            if ($timestampStart === false) {
                continue;
            }

            // 1. Obliczanie progów czasowych dla ogłoszeń (30, 10 i 1 minuta przed)
            $time30MinBefore = date("H:i", strtotime("-30 minutes", $timestampStart));
            $time10MinBefore = date("H:i", strtotime("-10 minutes", $timestampStart));
            $time1MinBefore = date("H:i", strtotime("-1 minutes", $timestampStart));

            // Obsługa ogłoszeń na czacie
            if ($currentTime === $time30MinBefore) {
                $this->broadcastNotification($eventData["name"], 30);
                $this->lastProcessedMinute = $currentTime;
                return;
            }

            if ($currentTime === $time10MinBefore) {
                $this->broadcastNotification($eventData["name"], 10);
                $this->lastProcessedMinute = $currentTime;
                return;
            }

            if ($currentTime === $time1MinBefore) {
                $this->broadcastNotification($eventData["name"], 1);
                $this->lastProcessedMinute = $currentTime;
                return;
            }

            // Automatyczne uruchamianie eventu dokładnie o zaplanowanej godzinie
            if ($currentTime === $scheduledTime) {

                // Tworzymy nową encję CompetitiveEvent w locie na bazie konfiguracji
                $event = new CompetitiveEvent(
                    $eventData["id"],
                    $eventData["name"],
                    $eventData["mode"],
                    $scheduledTime,
                    $eventData["rewards"],
                    false // Domyślnie wyłączona zmiana ELO zgodnie z wytycznymi "bez ELO zmiany"
                );

                $this->eventManager->startEvent($event);
                $this->lastProcessedMinute = $currentTime;
                return;
            }
        }
    }

    /**
     * Pomocniczy komunikat na czacie o zbliżającym się evencie.
     */
    public function broadcastNotification(string $eventName, int $minutesLeft): void {
        Server::getInstance()->broadcastMessage("[EVENT] Wydarzenie specjalne {$eventName} rozpocznie się za {$minutesLeft} min. ! Przygotujcie się!");
    }
}
