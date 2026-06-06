<?php

declare(strict_types=1);

namespace Application\Arena;

use pocketmine\player\Player;
use pocketmine\Server;

/**
 * TODO: Odpowiada za proces przygotowania i czyszczenia areny przed i po walce.
 * Wykonuje reset terenu (map reset) oraz ustawia graczy na pozycjach startowych.
 * Zapewnia, że każda walka odbywa się na czystej, niezniszczonej mapie.
 */
class ArenaLifecycle {

    private ArenaPool $arenaPool;

    public function __construct(ArenaPool $arenaPool) {
        $this->arenaPool = $arenaPool;
    }

    /**
     * Przygotowuje arenę do walki i teleportuje graczy na przypisane pozycje startowe.
     */
    public function prepareArena(string $worldName, Player $player1, Player $player2): void {
        $worldManager = Server::getInstance()->getWorldManager();

        // Upewniamy się, że świat jest załadowany w pamięci serwera
        if (!$worldManager->isWorldLoaded($worldName)) {
            $worldManager->loadWorld($worldName);
        }

        // Pobieramy punkty startowe skonfigurowane w AsyncPool
        $spawn1 = $this->arenaPool->getSpawn1($worldName);
        $spawn2 = $this->arenaPool->getSpawn2($worldName);

        // Teleportujemy graczy na pozycje startowe walki
        if ($spawn1 !== null) {
            $player1->teleport($spawn1);
        }
        if ($spawn2 !== null) {
            $player2->teleport($spawn2);
        }
    }

    /**
     * Czyści arenę po walce (Map Reset) przy użyciu szybkiego przeładowania świata.
     * Zwalnia również status areny w puli, żeby Matchmaker mógł ją znowu wybrać.
     */
    public function resetArena(string $worldName): void {
        $worldManager = Server::getInstance()->getWorldManager();
        $world = $worldManager->getWorldByName($worldName);

        if ($world !== null) {
            // Wykopujemy ewentualnych pozostałych graczy (np. widzów) na domyślny spawn serwera, żeby móc bezpiecznie wyładować świat.
            foreach ($world->getPlayers() as $player) {
                $player->teleport($worldManager->getDefaultWorld()->getSpawnLocation());
            }

            // Wyładowujemy świat bez zapisywania zmian (false), co cofa zniszczenia terenu.
            $worldManager->unloadWorld($world, false);
        }

        // Ładujemy świeżą, Nienaruszoną mapę prosto z plików serwera
        $worldManager->loadWorld($worldName);

        // Zgłaszamy do ArenaPool, że mapa jest znowu czysta i wolna do gry.
        $this->arenaPool->releaseArena($worldName);
    }
}
