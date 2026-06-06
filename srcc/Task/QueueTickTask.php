<?php

declare(strict_types=1);

namespace Task;

use pocketmine\Server;
use pocketmine\scheduler\Task;
use Application\Match\MatchManager;
use Application\Matchmaking\Matchmaker;
use Application\Matchmaking\QueueManager;
use Application\Player\SessionManager;
use Infrastructure\Database\PlayerRepository;

/**
 * Repeating task responsible for running matchmaking logic.
 *
 * Runs every second (20 ticks) and:
 *   1. Runs Matchmaker to find compatible pairs
 *   2. Creates matches for found pairs (MatchManager)
 *   3. Teleports players to arenas
 *   4. Expands ELO search window if no matches found (cooldown mechanism)
 *
 * Called from Bootstrap::registerTasks():
 *   $scheduler->scheduleRepeatingTask(new QueueTickTask(), 20);
 */
class QueueTickTask extends Task {

    private int $noMatchTicks = 0;
    private const NO_MATCH_THRESHOLD = 30; // Expand ELO every 30 seconds with no matches

    public function __construct(
        private readonly QueueManager      $queueManager,
        private readonly Matchmaker        $matchmaker,
        private readonly MatchManager      $matchManager,
        private readonly SessionManager    $sessionManager,
        private readonly PlayerRepository  $playerRepository,
    ) {}

    /**
     * Called every 20 ticks (1 second).
     * Attempts to match players from queue.
     */
    public function onRun(): void {
        $server = Server::getInstance();

        // Find all possible matches across all game modes
        $matches = $this->matchmaker->findAllMatches();

        if (empty($matches)) {
            // No matches found – increment counter
            $this->noMatchTicks++;

            // If no matches for too long, expand ELO search window
            if ($this->noMatchTicks >= self::NO_MATCH_THRESHOLD) {
                $this->queueManager->expandEloRanges(50); // Expand by 50 ELO points
                $this->noMatchTicks = 0;
            }
            return;
        }

        // Reset no-match counter when we find matches
        $this->noMatchTicks = 0;

        // Create matches for all found pairs
        foreach ($matches as $gameMode => $pairs) {
            foreach ($pairs as $pair) {
                $playerA = $server->getPlayerByPrefix($pair->playerAName);
                $playerB = $server->getPlayerByPrefix($pair->playerBName);

                if ($playerA === null || $playerB === null) {
                    // One of the players went offline – skip
                    // They'll be re-added to queue if they reconnect
                    continue;
                }

                // Load profiles for both players
                $profileA = $this->sessionManager->getSessionByUuid($pair->playerAUuid);
                $profileB = $this->sessionManager->getSessionByUuid($pair->playerBUuid);

                if ($profileA === null || $profileB === null) {
                    // Profiles not loaded yet – skip this pair
                    continue;
                }

                // Create match
                $match = $this->matchManager->createMatch(
                    pair:     $pair,
                    profileA: $profileA,
                    profileB: $profileB,
                    arenaId:  null, // TODO: Arena selection logic
                );

                if ($match === null) {
                    // Match creation failed
                    continue;
                }

                // Send match start messages
                $playerA->sendMessage("§a✓ §fMatch found! Opponent: §b{$pair->playerBName} §7(ELO: §f{$pair->playerBElo}§7)");
                $playerB->sendMessage("§a✓ §fMatch found! Opponent: §b{$pair->playerAName} §7(ELO: §f{$pair->playerAElo}§7)");

                // TODO: Teleport players to arena and initialize arena
                // For now, just send messages
            }
        }
    }
}
