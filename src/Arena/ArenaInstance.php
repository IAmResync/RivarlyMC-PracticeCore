<?php

declare(strict_types=1);

namespace Arena;

use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * TODO: Reprezentuje fizyczną przestrzeń świata, w której odbywa się mecz.
 * Zarządza granicami areny oraz blokowaniem niszczenia bloków przez graczy.
 * Odpowiada za teleportację uczestników na wyznaczone pozycje startowe.
 */
class ArenaInstance {

    private string $id;
    private World $world;

    // Granice areny (Bounding Box) do sprawdzania dozwolonej strefy walki
    private Position $minBoundaryPosition;
    private Position $maxBoundaryPosition;

    // Punkty startowe (Spawny dla zawodników)
    private Position $spawnPos1;
    private Position $spawnPos2;

    /** @var string[] Lista nazw graczy aktualnie walczących na tej arenie*/
    private array $activePlayers = [];

    public function __construct(
        string $id,
        World $world,
        Position $minBoundaryPosition,
        Position $maxBoundaryPosition,
        Position $spawnPos1,
        Position $spawnPos2
    ) {
        $this->id = $id;
        $this->world = $world;
        $this->minBoundaryPosition = $minBoundaryPosition;
        $this->maxBoundaryPosition = $maxBoundaryPosition;
        $this->spawnPos1 = $spawnPos1;
        $this->spawnPos2 = $spawnPos2;
    }

    /**
     * Teleportuje graczy na przypisane pozycje startowe i rejestruje ich w instancji.
     */
    public function preparePlayers(Player $player1, Player $player2): void {
        $this->activePlayers = [
            $player1->getName(),
            $player2->getName()
        ];

        $player1->teleport($this->spawnPos1);
        $player2->teleport($this->spawnPos2);

        $player1->sendMessage("Rozpoczęto pojedynek! Jesteś na arenie nr {$this->id}.");
        $player2->sendMessage("Rozpoczęto pojedynek! Jesteś na arenie nr {$this->id}.");
    }

    /**
     * Sprawdza. czy modyfikowany blok znajduje się w granicach tej areny.
     * Używane w listenerach zdarzeń do blokowania niszczenia świata.
     */
    public function isBlockInsideBounds(Block $block): bool {
        $pos = $block->getPosition();

        if ($pos->getWorld()->getFolderName() !== $this->world->getFolderName()) {
            return false;
        }

        return $pos->getX() >= $this->minBoundaryPosition->getX() && $pos->getX() <= $this->maxBoundaryPosition->getX()
            && $pos->getY() >= $this->minBoundaryPosition->getY() && $pos->getY() <= $this->maxBoundaryPosition->getY()
            && $pos->getZ() >= $this->minBoundaryPosition->getZ() && $pos->getZ() <= $this->maxBoundaryPosition->getZ();
    }

    /**
     * Sprawdza, czy gracz należy do tej instancji areny.
     */
    public function hasPlayer(Player $player): bool {
        return  in_array($player->getName(), $this->activePlayers, true);
    }

    /**
     * Czyści stan instancji areny po zakończeniu walki.
     */
    public function clearArena(): void {
        $this->activePlayers = [];
    }

    public function getId(): string {
        return $this->id;
    }

    public function getWorld(): World {
        return $this->world;
    }
}
