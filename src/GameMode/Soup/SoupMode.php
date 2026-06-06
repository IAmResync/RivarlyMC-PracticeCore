<?php

declare(strict_types=1);

namespace GameMode\Soup;

use GameMode\AbstractGameMode;
use Domain\GameMode\GameModeConfig;

/**
 * Soup game mode â€” players heal by consuming Mushroom Stew (+4 HP per bowl).
 * No splash potions. Pure reflex and soup management.
 *
 * Kit: iron sword, full inventory of mushroom stew, leather armor.
 * Win: opponent dies (HP reaches 0).
 * Heal: PlayerItemConsumeEvent on mushroom stew â†’ +4 HP, replace with empty bowl.
 */
final class SoupMode extends AbstractGameMode {

    public function __construct() {
        parent::__construct('soup', false, false);
    }

    public function getName(): string        { return 'soup'; }
    public function getDisplayName(): string { return 'Â§2Soup'; }

    public function getConfig(): GameModeConfig {
        return new GameModeConfig(matchDuration: 600, startingHealth: 20.0);
    }

    public function getInventoryTemplate(): array {
        return [
            0 => $this->buildItem('minecraft:iron_sword', enchants: ['sharpness' => 1]),
        ];
    }

    public function getArmorTemplate(): array {
        return [
            'helmet'     => $this->buildItem('minecraft:leather_helmet'),
            'chestplate' => $this->buildItem('minecraft:leather_chestplate'),
            'leggings'   => $this->buildItem('minecraft:leather_leggings'),
            'boots'      => $this->buildItem('minecraft:leather_boots'),
        ];
    }

    public function getMatchLongEffects(): array { return []; }

    /** HP restored per mushroom stew consumption */
    public function getHealAmount(): float { return 4.0; }
}

