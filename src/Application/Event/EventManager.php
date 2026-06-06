<?php

declare(strict_types=1);

namespace Application\Event;

use pocketmine\Server;
use Infrastructure\Database\PlayerRepository;

/**
 * TODO: Aktywuje i dezaktywuje competitive eventy oraz informuje graczy o nich.
 * Zarządza stanem aktywnego eventu i blokuje uruchomienie dwóch naraz.
 * Po zakończeniu eventu rozdziela nagrody i zapisuje wyniki do bazy.
 */
class EventManager {

    private ?CompetitiveEvent $activeEvent = null;
    private PlayerRepository $playerRepository;

    public function __construct(PlayerRepository $playerRepository) {
        $this->playerRepository = $playerRepository;
    }

    /**
     * Aktywuje specjalny event konkurencyjny
     * Blokuje uruchomienie drugiego wydarzenia, jeśli jedno już trwa.
     */
    public function startEvent(?CompetitiveEvent $event): void {

        if ($this->activeEvent !== null) {
            // Blokada: nie pozwalamy na uruchomienie dwóch eventów naraz
            Server::getInstance()->getLogger()->warning("[EventManager] Próba uruchomienia eventu '{$event->getName()}', podczas gdy trwa już '{$this->activeEvent->getName()}'!");
            return;
        }

        $this->activeEvent = $event;

        // Informujemy graczy na czacie serwera
        Server::getInstance()->broadcastMessage("[EVENT] Wystartowało wydarzenie specjalne: {$event->getName()}!");
        Server::getInstance()->broadcastMessage("Tryb: {$event->getGameMode()} | Sprawdż zasady i dołącz do gry!");
    }

    /**
     * Dezaktywuje obecny event, rozdaje nagrody topce i zapisuje wyniki.
     * @param string[] $winners Zwycięzcy ułożeni pozycjami (indeks 0 = 1 miejsce, itd.)
     */
    public function stopActiveEvent(array $winners): void {
        if ($this->activeEvent === null) {
            return;
        }

        Server::getInstance()->broadcastMessage("[EVENT] Wydarzenie '{$this->activeEvent->getName()}' dobiegło końca!");

        // 1. Rozdzielenie nagród zdefiniowanych w encji CompetitiveEvent
        $rewards = $this->activeEvent->getRewards();
        foreach ($winners as $index => $winnerName) {
            $rank = $index + 1;
            $player = Server::getInstance()->getPlayerExact($winnerName);

            if ($player !== null && isset($rewards["top{$rank}"])) {
                $rewardMessage = $rewards["top{$rank}"];
                $player->sendMessage("Gratulacje! Za zajęcie {$rank} miejsca w evencie otrzymujesz: {$rewardMessage}!");
            }
        }

        // 2. Zapisywanie wyników do bazy danych przez PlayerRepository
        // Ponieważ event może mieć zasadę "bez ELO zmian", sprawdzamy flagę w encji
        if ($this->activeEvent->isEloChangesEnabled()) {
            foreach ($winners as $index => $winnerName) {
                // Jeśli event modyfikuje ELO, dodajemy punkty zwycięzcą (np. +50 ELO dla pierwszego miejsca)
                if ($index === 0) {
                    // W prawdziwym kodzie najpierw załadujesz profil przez loadProfile, zmodyfikujesz i zapiszesz
                    $this->playerRepository->saveProfile($winnerName, 1050, 1, 0);
                }
            }
        }

        // Czyścimy stan - slot na kolejny event jest wolny
        $this->activeEvent = null;
    }

    /**
     * Zwraca aktualnie trwający event lub null, jeśli nic się nie dzieje!
     */
    public function getActiveEvent(): ?CompetitiveEvent {
        return $this->activeEvent;
    }
}
