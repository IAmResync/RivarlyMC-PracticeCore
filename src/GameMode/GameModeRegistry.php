<?php

declare(strict_types=1);

namespace GameMode;

/**
 * TODO: Katalog wszystkich zarejestrowanych i dostępnych trybów gry w pluginie.
 * Umożliwia pobieranie instancji trybu na podstawie jego nazwy (np. "Nodebuff").
 * Służy jako punkt centralny przy dodawaniu nowych zestawów umiejętności i zasad.
 */
class GameModeRegistry {

    /**
     * Tablica przechowująca zarejestrowane tryby gry: [nazwa_trybu => Instancja]
     * @var array<string, AbstractGameMode>
     */
    private array $gameModes = [];

    /**
     * Rejestruje nowy tryb gry w systemie.
     */
    public function registerMode(AbstractGameMode $gameMode): void {
        $lowerName = strtolower($gameMode->getName());

        if (isset($this->gameModes[$lowerName])) {
            throw new \InvalidArgumentException("Tryb gry o nazwie '{$gameMode->getName()}' jest już zarejestrowany!");
        }
        $this->gameModes[$lowerName] = $gameMode;
    }

    /**
     * Pobiera instancję trybu gry na podstawie jego nazwy.
     */
    public function getMode(string $name): ?AbstractGameMode {
        return $this->gameModes[strtolower($name)] ?? null;
    }

    /**
     * Sprawdza, czy tryb gry o podanej nazwie istnieje w rejestrze.
     */
    public function exists(string $name): bool {
        return isset($this->gameModes[strtolower($name)]);
    }

    /**
     * Zwraca wszystkie zarejestrowane tryby gry.
     *
     * @return array<string, AbstractGameMode>
     */
    public function getAllModes(): array {
        return $this->gameModes;
    }

    /**
     * Wyrejestrowuje tryb gry z systemu (przydatne przy przeładowaniach pluginu).
     */
    public function unregisterMode(string $name): void {
        unset($this->gameModes[strtolower($name)]);
    }
}
