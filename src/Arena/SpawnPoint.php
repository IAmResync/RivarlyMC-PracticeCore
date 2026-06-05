<?php

declare(strict_types=1);

namespace Arena;

use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * TODO: Obiekt przechowujący współrzędne i rotację punktu odrodzenia na arenie.
 * Służy do precyzyjnego ustawiania graczy na początku meczu lub po respawnie.
 * Zapewnia spójny sposób reprezentacji pozycji geograficznych w ramach systemu map.
 */
class SpawnPoint {

    private Position $position;
    private float $yaw;
    private float $pitch;

    public function __construct(Position $position, float $yaw = 0.0, float $pitch = 0.0) {
        $this->position = $position;
        $this->yaw = $yaw;
        $this->pitch = $pitch;
    }

    /**
     * Zwraca obiekt Position PocketMine.
     */
    public function getPosition(): Position {
        return $this->position;
    }

    /**
     * Zwraca współrzędną Z.
     */
    public function getZ(): float {
        return $this->position->getZ();
    }

    /**
     * Zwraca świat (World)), w ktorym znajduje się punkt odrodzenia.
     */
    public function getWorld(): World {
        return $this->position->getWorld();
    }

    /**
     * Zwraca rotację pionową (spojrzenie góra/dół).
     */
    public function getPitch(): float {
        return $this->pitch;
    }

    /**
     * Pomocnicza metoda do szybkiego tworzenia obiektu na podstawie surowych danych (np. z pliku konfiguracyjnego .yml).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, World $world): self {
        $position = new Position(
            (float) ($data["x"] ?? 0.0),
            (float) ($data["y"] ?? 0.0),
            (float) ($data["z"] ?? 0.0),
            $world
        );

        return new self(
            $position,
            (float) ($data["yaw"] ?? 0.0),
            (float) ($data["pitch"] ?? 0.0)
        );
    }
}
