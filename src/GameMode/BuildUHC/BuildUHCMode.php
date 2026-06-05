<?php

declare(strict_types=1);

namespace GameMode\BuildUHC;

use GameMode\AbstractGameMode;
use Domain\GameMode\GameModeConfig;

/**
 * BuildUHC game mode — players can place limited blocks during the fight.
 * Full diamond armor + sword + bow + blocks (sand/wood).
 * Win: kill the opponent. All placed blocks are removed after match.
 */
final class BuildUHCMode extends AbstractGameMode {

    /** @var array<string, ArenaBlockTracker> matchId => tracker */
    private array $trackers = [];

    public function getName(): string        { return 'build_uhc'; }
    public function getDisplayName(): string { return '§dBuild UHC'; }

    public function getConfig(): GameModeConfig {
        return new GameModeConfig(matchDuration: 600, startingHealth: 20.0);
    }

    public function getInventoryTemplate(): array {
        return [
            0 => $this->buildItem('minecraft:diamond_sword', enchants: ['sharpness' => 1]),
            1 => $this->buildItem('minecraft:bow',           enchants: ['power' => 1]),
            2 => $this->buildItem('minecraft:arrow',         count: 16),
            3 => $this->buildItem('minecraft:sandstone',     count: 64),
            4 => $this->buildItem('minecraft:planks',        count: 64),
            5 => $this->buildItem('minecraft:golden_apple',  count: 4),
        ];
    }

    public function getArmorTemplate(): array {
        return [
            'helmet'     => $this->buildItem('minecraft:diamond_helmet',     enchants: ['protection' => 1]),
            'chestplate' => $this->buildItem('minecraft:diamond_chestplate', enchants: ['protection' => 1]),
            'leggings'   => $this->buildItem('minecraft:diamond_leggings',   enchants: ['protection' => 1]),
            'boots'      => $this->buildItem('minecraft:diamond_boots',      enchants: ['protection' => 1]),
        ];
    }

    public function getMatchLongEffects(): array { return []; }

    // -----------------------------------------------------------------------
    // Block tracking
    // -----------------------------------------------------------------------

    public function createTracker(string $matchId): ArenaBlockTracker {
        $tracker = new ArenaBlockTracker();
        $this->trackers[$matchId] = $tracker;
        return $tracker;
    }

    public function getTracker(string $matchId): ?ArenaBlockTracker {
        return $this->trackers[$matchId] ?? null;
    }

    public function onMatchEnd(string $matchId, \pocketmine\world\World $world): void {
        $this->trackers[$matchId]?->cleanup($world);
        unset($this->trackers[$matchId]);
    }
}
