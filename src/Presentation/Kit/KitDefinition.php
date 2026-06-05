<?php

declare(strict_types=1);

namespace Presentation\Kit;

use Domain\GameMode\GameModeInterface;

/**
 * TODO: Immutable value object opisujący kompletny zestaw startowy dla danego trybu gry.
 * Definiuje każdy slot inwentarza, zbroję, efekty potek i dodatkowe właściwości.
 * Używany przez KitManager do szybkiego wyposażenia gracza na starcie meczu.
 */
final class KitDefinition {

    /**
     * @param array<int, array<string, mixed>> $inventorySlots [slot => ['id' => ..., 'count' => ...]]
     * @param array<int, array<string, mixed>> $armorSlots     [0 => ['id' => ...], 1 => ...]
     * @param array<string, int>               $effects        ['speed' => 2, 'regeneration' => 1]
     */
    public function __construct(
        public readonly string $gameModeName,
        public readonly array  $inventorySlots,
        public readonly array  $armorSlots,
        public readonly array  $effects,
    ) {}

    /**
     * Tworzy definicję kitu na podstawie danych z interfejsu trybu gry.
     */
    public static function fromGameMode(GameModeInterface $mode): self {
        return new self(
            gameModeName:   $mode->getName(),
            inventorySlots: $mode->getInventoryTemplate(),
            armorSlots:     $mode->getArmorTemplate(),
            effects:        $mode->getMatchLongEffects()
        );
    }

    public function hasArmor(): bool {
        return count($this->armorSlots) > 0;
    }

    public function hasEffects(): bool {
        return count($this->effects) > 0;
    }
}