<?php

declare(strict_types=1);

namespace GameMode\Sumo;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use Combat\KnockbackEngine;
use Application\Match\MatchManager;
use Application\Player\SessionManager;
use Domain\GameMode\GameModeConfig;

/**
 * Handles all Sumo-specific event logic.
 *
 * Three responsibilities:
 * 1. DAMAGE CANCEL — all HP damage is blocked in Sumo matches
 * 2. CUSTOM KNOCKBACK — applies SumoConfig::knockbackMultiplier on every hit
 * 3. VOID DETECTION — on PlayerMoveEvent, checks if Y < voidYThreshold
 * and if so ends the match (the fallen player loses)
 *
 * Registration (same pattern as NodebuffListener):
 * $listener = new SumoListener(
 * mode:          $sumoMode,
 * matchManager:  $matchManager,
 * sessionManager: $sessionManager,
 * knockback:     $knockbackEngine,
 * isSumoPlayer:  fn(string $uuid) => ...,
 * );
 */
final class SumoListener implements Listener {

    /**
     * @param callable(string $uuid): bool $isSumoPlayer
     * Returns true if the given UUID is currently in a Sumo match.
     */
    public function __construct(
        private readonly SumoMode        $mode,
        private readonly MatchManager    $matchManager,
        private readonly SessionManager  $sessionManager,
        private readonly KnockbackEngine $knockback,
        private readonly mixed           $isSumoPlayer,
    ) {}

    // -----------------------------------------------------------------------
    // 1. Block all HP damage in Sumo
    // -----------------------------------------------------------------------

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;

        $uuid = $entity->getUniqueId()->toString();
        if (!($this->isSumoPlayer)($uuid)) return;

        // Cancel ALL damage — void, fall, hit, fire — nothing kills in Sumo
        // (void loss is handled by PlayerMoveEvent instead)
        $event->cancel();
    }

    // -----------------------------------------------------------------------
    // 2. Apply custom knockback on hit
    // -----------------------------------------------------------------------

    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        $victim   = $event->getEntity();
        $attacker = $event->getDamager();

        if (!$victim instanceof Player || !$attacker instanceof Player) return;

        $attackerUuid = $attacker->getUniqueId()->toString();
        if (!($this->isSumoPlayer)($attackerUuid)) return;

        // Cancel real damage (already handled above, but explicit here)
        $event->cancel();

        // Apply boosted knockback using SumoMode multiplier
        $this->knockback->applyKnockbackWithMultiplier(
            $attacker,
            $victim,
            $this->mode->getKnockbackMultiplier(),
        );
    }

    // -----------------------------------------------------------------------
    // 3. Void detection — player falls below Y threshold
    // -----------------------------------------------------------------------

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $uuid   = $player->getUniqueId()->toString();

        if (!($this->isSumoPlayer)($uuid)) return;

        $y = $player->getPosition()->getY();
        if ($y > $this->mode->getVoidYThreshold()) return;

        // This player fell — they lose
        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match === null || !$match->isActive()) return;

        $winnerUuid = $match->getOpponentUuid($uuid);
        if ($winnerUuid === null) return;

        $loserProfile  = $this->sessionManager->getSessionByUuid($uuid);
        $winnerProfile = $this->sessionManager->getSessionByUuid($winnerUuid);

        if ($loserProfile === null || $winnerProfile === null) return;

        // Teleport fallen player back up so they don't spam the event
        $spawn = $player->getWorld()->getSpawnLocation();
        $player->teleport($spawn);

        $this->matchManager->endMatch(
            matchId:       $match->getMatchId(),
            winnerUuid:    $winnerUuid,
            winnerProfile: $winnerProfile,
            loserProfile:  $loserProfile,
        );
    }
}