<?php

declare(strict_types=1);

namespace Task;

use pocketmine\scheduler\Task;
use Application\Match\MatchLifecycle;
use Application\Match\MatchManager;
use Core\Container;

/**
 * Repeating task responsible for live match logic.
 *
 * Runs every second (20 ticks) and:
 *   1. Ticks all active MatchLifecycle instances
 *   2. Updates countdowns, grace periods, and match timers
 *   3. Checks win conditions and updates scoreboard
 *   4. Ensures smooth PvP gameplay
 *
 * Called from Bootstrap::registerTasks():
 *   $scheduler->scheduleRepeatingTask(new MatchTickTask(), 20);
 *
 * Note: Container is injected via MatchTickTask constructor OR
 * retrieved from Plugin::getContainer() in static context.
 */
class MatchTickTask extends Task {

    /** @var array<string, MatchLifecycle> matchId => lifecycle */
    private array $lifecycles = [];

    public function __construct(
        private readonly MatchManager $matchManager,
    ) {}

    /**
     * Called every 20 ticks (1 second).
     * Updates all active match lifecycles.
     */
    public function onRun(): void {
        $allMatches = $this->matchManager->getAllActiveMatches();

        foreach ($allMatches as $matchId => $match) {
            // Skip already ended matches
            if ($match->isFinished()) {
                continue;
            }

            // Get or create lifecycle for this match
            $lifecycle = $this->lifecycles[$matchId] ?? null;
            if ($lifecycle === null) {
                // Create lifecycle on first tick
                $lifecycle = new MatchLifecycle(
                    match: $match,
                    config: $this->getConfig(), // TODO: Pass from Container
                    onCountdownTick: function(int $secondsLeft) use ($match) {
                        $this->broadcastToMatch($match, "§6⏱ Starting in §f$secondsLeft§6 seconds...");
                    },
                    onMatchStart: function() use ($match) {
                        $this->broadcastToMatch($match, "§a► §fMatch started!");
                    },
                    onGracePeriodEnd: function() use ($match) {
                        $this->broadcastToMatch($match, "§c► §fPvP is now enabled!");
                    },
                    onTimeout: function() use ($match, $matchId) {
                        // Match timeout – random winner or tie (handle in MatchManager)
                        $this->broadcastToMatch($match, "§e⏱ Time limit reached!");
                        // TODO: Handle timeout logic (random winner or force end)
                    },
                );
                $this->lifecycles[$matchId] = $lifecycle;
            }

            // Tick the lifecycle
            $lifecycle->tick();

            // Update match state in manager
            if ($lifecycle->isFinished()) {
                unset($this->lifecycles[$matchId]);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Broadcast a message to all players in a match (both players and spectators).
     */
    private function broadcastToMatch(\Domain\Match\GameMatch $match, string $message): void {
        $server = \pocketmine\Server::getInstance();

        // Send to all players in the match
        foreach ($match->getPlayers() as $uuid => $name) {
            $player = $server->getPlayerByPrefix($name);
            if ($player !== null) {
                $player->sendMessage($message);
            }
        }

        // Send to all spectators
        foreach ($match->getSpectators() as $uuid => $name) {
            $player = $server->getPlayerByPrefix($name);
            if ($player !== null) {
                $player->sendMessage($message);
            }
        }
    }

    /**
     * TODO: Get config from Container.
     * This is a placeholder – in real implementation, config comes from DI.
     */
    private function getConfig() {
        // For now, return null – will be fixed when Container is passed
        return null;
    }
}
