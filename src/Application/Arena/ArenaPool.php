<?php

declare(strict_types=1);

namespace Application\Arena;

use pocketmine\Server;
use pocketmine\world\Position;

/**
 * TODO: Zarządza pulą dostępnych map i monitoruje, które z nich są zajęte.
 * Udostępnia wolne areny dla systemu Matchmakingu przy tworzeniu meczów.
 * Przechowuje szablony (schematics) i metadane dotyczące punktów spawnu.
 */
class ArenaPool {

    /** @var array<string, array{status: string, spawn1: Position, spawn2: Position, schematic: string}> */
    private array $arenas = [];

    public function __construct() {
        $this->registerArena("Arena_1v1_1", "classic_1v1.schem");
        $this->registerArena("Arena_1v1_2", "desert_1v1.schem");
    }

    /**
     * Rejestruje nową arenę w puli wraz z jej metadanymi.
     */
    public function registerArena(string $worldName, string $schematicName): void {
        $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);

        // Definiujemy domyślne punkty spawnu na bazie świata mapy
        $spawn1 = $world !== null ? new Position(10, 64, 10, $world): null;
        $spawn2 = $world !== null ? new Position(20, 64, 20, $world): null;

        // Jeśli świat nie jest załadowany, używamy domyślnego świata serwera (zabezpieczenie)
        $fallbackWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();

        $this->arenas[$worldName] = [
            "status" => "FREE",
            "spawn1" => $spawn1 ?? new Position(10, 64, 10, $fallbackWorld),
            "spawn2" => $spawn2 ?? new Position(20, 64, 20, $fallbackWorld),
            "schematic" => $schematicName
        ];
    }

    /**
     * Pobiera pierwszą wolną arenę z puli i rezerwuje ją (zmienia status na BUSY).
     * Idealne dla systemu Matchmakingu od mojego ziomka Resync
     * * @return string|null Zwraca nazwę świata areny lub null, jeśli wszystkie są zajęte.
     */
    public function findAndReserveFreeArena(): ?string {
        foreach ($this->arenas as $worldName => $data) {
            if ($data["status"] === "FREE") {
                $this->arenas[$worldName]["status"] = "BUSY";
                return $worldName;
            }
        }
        return null;
    }

    /**
     * Zwalnia arenę po zakończeniu meczu, aby mogła być użyta ponownie.
     */
    public function releaseArena(string $worldName): void {
        if (isset($this->arenas[$worldName])) {
            $this->arenas[$worldName]["status"] = "FREE";
        }
    }

    /**
     * Pobiera punkt spawnu dla pierwszego gracza na danej arenie.
     */
    public function getSpawn1(string $worldName): ?Position {
        return $this->arenas[$worldName]["spawn1"] ?? null;
    }

    /**
     * Pobiera punkt spawnu dla drugiego gracza na danej arenie.
     */
    public function getSpawn2(string $worldName): ?Position {
        return $this->arenas[$worldName]["spawn2"] ?? null;
    }

    /**
     * Pobiera nazwę szablonu (schematic) powiązanego z areną.
     */
    public function getSchematic(string $worldName): ?string {
        return $this->arenas[$worldName]["schematic"] ?? null;
    }

    /**
     * Zwraca pęłną listę zajerestrowanych aren i ich statusów.
     */
    public function getAllArenas(): array {
        return $this->arenas;
    }
}
