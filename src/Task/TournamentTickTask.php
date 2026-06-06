<?php

declare(strict_types=1);

namespace Task;

use pocketmine\Server;
use pocketmine\scheduler\Task;
use Application\Tournament\TournamentManager;

/**
 * Repeating task responsible for tournament clock and bracket management.
 *
 * Runs every second (20 ticks) and:
 *   1. Controls tournament timer and round breaks
 *   2. Handles timeouts for players who don't join their bracket matches
 *   3. Updates global tournament status for all players on server
 *   4. Transitions between rounds automatically
 *
 * Called from Bootstrap::registerTasks():
 *   $scheduler->scheduleRepeatingTask(new TournamentTickTask(), 20);
 */
class TournamentTickTask extends Task {

    private int $roundSeconds = 0;
    private int $currentRound = 0;
    private const ROUND_DURATION = 300; // 5 minutes per round
    private const BREAK_DURATION = 60;  // 1 minute break between rounds

    public function __construct(
        private readonly TournamentManager $tournamentManager,
    ) {}

    /**
     * Called every 20 ticks (1 second).
     * Updates tournament state.
     */
    public function onRun(): void {
        $status = $this->tournamentManager->getStatus();

        // Only tick if tournament is active
        if ($status !== "ACTIVE") {
            return;
        }

        $currentRound = $this->tournamentManager->getCurrentRound();

        // Track round timer
        $this->roundSeconds++;

        // Broadcast tournament status every 30 seconds
        if ($this->roundSeconds % 30 === 0) {
            $this->broadcastTournamentStatus();
        }

        // Check round end conditions
        if ($this->roundSeconds >= self::ROUND_DURATION) {
            $this->endRound();
            $this->roundSeconds = 0;
        }
    }

    // -----------------------------------------------------------------------
    // Round Management
    // -----------------------------------------------------------------------

    /**
     * End current round and prepare for next one.
     */
    private function endRound(): void {
        $server = Server::getInstance();
        $currentRound = $this->tournamentManager->getCurrentRound();

        $server->broadcastMessage("§fRound §9" . $currentRound . "§fending...");

        // TODO: Determine round winners (from bracket results)
        // TODO: Advance winners to next round
        // TODO: Eliminate losers

        // For now, just announce the end
        $this->roundSeconds = 0;
    }

    /**
     * Handle player timeout (didn't join their bracket match).
     */
    private function handleTimeout(string $playerUuid): void {
        $server = Server::getInstance();
        $player = null;

        foreach ($server->getOnlinePlayers() as $p) {
            if ($p->getUniqueId()->toString() === $playerUuid) {
                $player = $p;
                break;
            }
        }

        if ($player !== null) {
            $player->sendMessage("§c✗ §fYou were eliminated for not joining your match!");
        }

        // TODO: Remove from tournament bracket
    }

    // -----------------------------------------------------------------------
    // Broadcasts
    // -----------------------------------------------------------------------

    /**
     * Broadcast current tournament status to all players.
     */
    private function broadcastTournamentStatus(): void {
        $server = Server::getInstance();
        $currentRound = $this->tournamentManager->getCurrentRound();
        $participants = $this->tournamentManager->getParticipants();
        $remainingPlayers = count($participants);

        $message = "§6[TOURNAMENT] §fRound §9" . $currentRound . "§f — §e" . $remainingPlayers . "§f players remaining";
        $server->broadcastMessage($message);
    }
}
