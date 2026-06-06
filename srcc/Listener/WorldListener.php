<?php

declare(strict_types=1);

namespace Listener;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use Application\Match\MatchManager;

/**
 * Monitors block and environmental changes on arena worlds.
 *
 * Responsibilities:
 *   1. BlockBreakEvent    → block block destruction on match arenas
 *   2. BlockPlaceEvent    → block block placement on match arenas
 *
 * Works with:
 *   - ArenaLifecycle      (manages arena instance)
 *   - MatchManager        (check if player is in a match)
 *   - WorldListener       (this listener)
 *
 * Registration (Bootstrap):
 *   $pm->registerEvents(new WorldListener($matchManager), $plugin);
 */
final class WorldListener implements Listener {

    public function __construct(
        private readonly MatchManager $matchManager,
    ) {}

    // -----------------------------------------------------------------------
    // Block Breaking
    // -----------------------------------------------------------------------

    /**
     * Called when a player attempts to break a block.
     * Players on match arenas cannot break blocks.
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $uuid = $player->getUniqueId()->toString();

        // If player is in a match on arena, block it
        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match !== null) {
            $event->cancel();
            $player->sendMessage("§c✗ §fYou cannot break blocks during a match!");
            return;
        }
    }

    // -----------------------------------------------------------------------
    // Block Placement
    // -----------------------------------------------------------------------

    /**
     * Called when a player attempts to place a block.
     * Players on match arenas cannot place blocks.
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $uuid = $player->getUniqueId()->toString();

        // If player is in a match on arena, block it
        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match !== null) {
            $event->cancel();
            $player->sendMessage("§c✗ §fYou cannot place blocks during a match!");
            return;
        }
    }
}
