<?php

declare(strict_types=1);

namespace GameMode\Boxing;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use Application\Match\MatchManager;

/**
 * Handles all Boxing-specific event logic.
 */
final class BoxingListener implements Listener {

    /**
     * @param BoxingMode   $mode
     * @param MatchManager $matchManager
     * @param callable(string $uuid): bool $isBoxingPlayer
     */
    public function __construct(
        private readonly BoxingMode   $mode,
        private readonly MatchManager $matchManager,
        private readonly mixed        $isBoxingPlayer,
    ) {}

    // -----------------------------------------------------------------------
    // Core hit handler
    // -----------------------------------------------------------------------

    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $victim   = $event->getEntity();
        $attacker = $event->getDamager();

        if (!$victim instanceof Player || !$attacker instanceof Player) return;

        $attackerUuid = $attacker->getUniqueId()->toString();
        $victimUuid   = $victim->getUniqueId()->toString();

        // Only handle Boxing players
        if (!($this->isBoxingPlayer)($attackerUuid)) return;

        // Cancel all real damage — Boxing uses hit counter, not HP
        $event->cancel();

        // Reset victim HP immediately so they never die
        $victim->setHealth($this->mode->getHealthResetValue());

        // Get match for this attacker
        $match = $this->matchManager->getMatchByPlayer($attackerUuid);
        if ($match === null || !$match->isActive()) return;

        $matchId = $match->getMatchId();

        // Record hit and check for winner
        $winnerUuid = $this->mode->processHit($matchId, $attackerUuid);

        // Update action bar with live score for both players
        $session = $this->mode->getSession($matchId);
        if ($session !== null) {
            $scoreLine = $session->getScoreLine();
            $attacker->sendActionBarMessage($scoreLine);
            $victim->sendActionBarMessage($scoreLine);
        }

        // End match if hit limit reached
        if ($winnerUuid !== null) {
            $loserUuid = $match->getOpponentUuid($winnerUuid);
            if ($loserUuid === null) return;

            $winnerProfile = $this->getProfile($winnerUuid);
            $loserProfile  = $this->getProfile($loserUuid);

            if ($winnerProfile === null || $loserProfile === null) return;

            $this->matchManager->endMatch(
                matchId:       $matchId,
                winnerUuid:    $winnerUuid,
                winnerProfile: $winnerProfile,
                loserProfile:  $loserProfile,
            );
        }
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function getProfile(string $uuid): ?\Rivarly\Domain\Player\PlayerProfile {
        // TODO: inject SessionManager and call getProfile($uuid)
        return null;
    }
}