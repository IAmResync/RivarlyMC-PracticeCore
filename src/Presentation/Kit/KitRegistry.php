<?php

declare(strict_types=1);

namespace Presentation\Kit;

use Domain\GameMode\GameModeInterface;
use InvalidArgumentException;

/**
 * TODO: Katalog wszystkich dostępnych kitów pogrupowany po nazwie trybu gry.
 * Rejestruje kity przy starcie pluginu (np. "nodebuff", "boxing", "sumo", "bedfight").
 * Udostępnia metodę getKit(string $gameMode): KitDefinition dla KitManagera.
 */
final class KitRegistry {

    /** * @var array<string, KitDefinition> lowercase_name => KitDefinition
     */
    private array $kits = [];

    /**
     * Buduje KitRegistry na podstawie wszystkich zarejestrowanych trybów gry.
     * Wywoływane jednorazowo przy starcie pluginu.
     *
     * @param array<string, GameModeInterface> $modes
     */
    public static function buildFrom(array $modes): self {
        $registry = new self();
        foreach ($modes as $mode) {
            $registry->register(KitDefinition::fromGameMode($mode));
        }
        return $registry;
    }

    /**
     * Rejestruje pojedynczą definicję zestawu w katalogu.
     */
    public function register(KitDefinition $kit): void {
        $this->kits[strtolower($kit->gameModeName)] = $kit;
    }

    /**
     * Udostępnia metodę getKit(string $gameMode): KitDefinition dla KitManagera.
     * (ZGODNIE Z WYMAGANIAMI TODO)
     */
    public function getKit(string $gameMode): KitDefinition {
        $lowercaseMode = strtolower($gameMode);

        if (!isset($this->kits[$lowercaseMode])) {
            throw new InvalidArgumentException(
                "Brak zarejestrowanego zestawu (kitu) dla trybu gry '{$gameMode}'. " .
                "Dostępne: " . implode(', ', array_keys($this->kits))
            );
        }

        return $this->kits[$lowercaseMode];
    }

    /**
     * Sprawdza, czy w katalogu istnieje zestaw dla danego trybu gry.
     */
    public function hasKit(string $gameMode): bool {
        return isset($this->kits[strtolower($gameMode)]);
    }

    /**
     * Zwraca całkowitą liczbę zarejestrowanych zestawów.
     */
    public function count(): int {
        return count($this->kits);
    }
}