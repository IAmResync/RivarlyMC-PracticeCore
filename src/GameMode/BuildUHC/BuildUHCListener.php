<?php

declare(strict_types=1);

namespace GameMode\BuildUHC;

use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;
use Application\Match\MatchManager;
use Application\Player\SessionManager;

/**
 * Handles BuildUHC-specific event logic.
 *
 * Two extra mechanics on top of the base CombatListener:
 *
 * 1. BlockPlaceEvent → register blocks in ArenaBlockTracker (cleanup after match)
 * 2. PlayerDeathEvent → respawn the player mid-arena with half inventory intact,
 *    record kill on attacker, subtract 1 life (if limited-lives variant is active)
 *
 * Note: Block breaking/placing protection is handled by WorldListener.
 * This listener only concerns itself with GAME LOGIC specific to BuildUHC
 * (tracking placements, death handling), not with permission checks.
 *
 * Registration in Bootstrap::register():
 *   $server->getPluginManager()->registerEvents(
 *       new BuildUHCListener($buildUhcMode, $matchManager, $sessionManager),
 *       $plugin
 *   );
 */
final class BuildUHCListener implements Listener {

    public function __construct(
        private readonly BuildUHCMode   $mode,
        private readonly MatchManager   $matchManager,
        private readonly SessionManager $sessionManager,
    ) {}

    // -----------------------------------------------------------------------
    // Block placement — record in tracker
    // -----------------------------------------------------------------------

    /**
     * Every block placed inside a BuildUHC match is registered so it can be
     * removed cleanly when the match ends.
     *
     * WorldListener::onBlockPlace() already guards the permission (only BuildUHC
     * players may place blocks); we just record here.
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $uuid   = $player->getUniqueId()->toString();

        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match === null) return;

        if (strtolower($match->getGameMode()) !== 'build_uhc') return;

        $tracker = $this->mode->getTracker($match->getMatchId());
        if ($tracker === null) return;

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $tracker->recordPlace($block->getPosition());
        }
    }

    // -----------------------------------------------------------------------
    // Player death — BuildUHC ends on first death (standard practice rules)
    // -----------------------------------------------------------------------

    /**
     * When a player dies in a BuildUHC match the match ends immediately.
     * The killer is the winner; all placed blocks are cleaned up by onMatchEnd.
     *
     * We cancel the death event so the player isn't sent to the death screen —
     * MatchManager::endMatch handles teleportation back to lobby instead.
     */
    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $victim = $event->getPlayer();
        $uuid   = $victim->getUniqueId()->toString();

        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match === null) return;

        if (strtolower($match->getGameMode()) !== 'build_uhc') return;

        // Suppress the death screen and drops
        $event->setDrops([]);
        $event->setXpDropAmount(0);
        $event->setDeathMessage('');

        $loserProfile  = $this->sessionManager->getProfile($uuid);
        if ($loserProfile === null) return;

        // Find the killer (may be null if environment kill / fall)
        $cause = $victim->getLastDamageCause();
        $killerUuid = null;

        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            if ($damager instanceof Player) {
                $killerUuid = $damager->getUniqueId()->toString();
            }
        }

        // If no killer found (void / fall), opponent wins by default
        if ($killerUuid === null) {
            $killerUuid = $match->getOpponentUuid($uuid);
        }

        if ($killerUuid === null) return;

        $winnerProfile = $this->sessionManager->getProfile($killerUuid);
        if ($winnerProfile === null) return;

        $this->matchManager->endMatch(
            matchId:       $match->getMatchId(),
            winnerUuid:    $killerUuid,
            winnerProfile: $winnerProfile,
            loserProfile:  $loserProfile,
        );
    }

    // -----------------------------------------------------------------------
    // Void protection — cancel void death, end match immediately
    // -----------------------------------------------------------------------

    public function onVoidDamage(EntityDamageEvent $event): void {
        if ($event->getCause() !== EntityDamageEvent::CAUSE_VOID) return;

        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;

        $uuid  = $entity->getUniqueId()->toString();
        $match = $this->matchManager->getMatchByPlayer($uuid);

        if ($match === null || strtolower($match->getGameMode()) !== 'build_uhc') return;

        // Cancel the void damage — the death handler above will fire via normal death flow,
        // but for void specifically we end match here to avoid double processing.
        $event->cancel();

        $loserProfile  = $this->sessionManager->getProfile($uuid);
        if ($loserProfile === null) return;

        $winnerUuid   = $match->getOpponentUuid($uuid);
        if ($winnerUuid === null) return;

        $winnerProfile = $this->sessionManager->getProfile($winnerUuid);
        if ($winnerProfile === null) return;

        $this->matchManager->endMatch(
            matchId:       $match->getMatchId(),
            winnerUuid:    $winnerUuid,
            winnerProfile: $winnerProfile,
            loserProfile:  $loserProfile,
        );

        $entity->sendTitle('§cYou fell!', '§7Your opponent wins by default.');
    }
}
