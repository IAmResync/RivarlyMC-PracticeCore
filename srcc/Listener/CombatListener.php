<?php

declare(strict_types=1);

namespace Listener;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;
use Combat\KnockbackEngine;
use Application\Match\MatchManager;
use Application\Player\SessionManager;
use Application\Player\StatsCollector;
use AntiCheat\HitValidator;

/**
 * Main point of interception for attack and damage events.
 * Integrates:
 *   1. KnockbackEngine       → applies knockback on hit
 *   2. HitValidator          → anti-cheat validation
 *   3. StatsCollector        → collects statistics for database
 *   4. MatchManager          → manages match state and winner
 *
 * Registration (Bootstrap):
 *   $pm->registerEvents(new CombatListener(...), $plugin);
 */
final class CombatListener implements Listener {

    public function __construct(
        private readonly KnockbackEngine  $knockbackEngine,
        private readonly MatchManager     $matchManager,
        private readonly SessionManager   $sessionManager,
        private readonly StatsCollector   $statsCollector,
        private readonly HitValidator     $hitValidator,
    ) {}

    // -----------------------------------------------------------------------
    // Main Event: EntityDamageByEntity
    // -----------------------------------------------------------------------

    /**
     * Called when one player attacks another.
     * Performs full logic: validation → damage calc → knockback → stats.
     */
    public function onEntityDamageByEntity(EntityDamageByEntityEvent $event): void {
        $victim   = $event->getEntity();
        $attacker = $event->getDamager();

        // Must work with players only
        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        $victimUuid   = $victim->getUniqueId()->toString();
        $attackerUuid = $attacker->getUniqueId()->toString();

        // 1. Check if both players are in the same match
        $attackerMatch = $this->matchManager->getMatchByPlayer($attackerUuid);
        $victimMatch   = $this->matchManager->getMatchByPlayer($victimUuid);

        if ($attackerMatch === null || $victimMatch === null) {
            // No match – block all damage (practice PvP server)
            $event->cancel();
            return;
        }

        if ($attackerMatch->getMatchId() !== $victimMatch->getMatchId()) {
            // In different matches – block
            $event->cancel();
            return;
        }

        $match = $attackerMatch;

        // 2. Check if match is active and PvP is enabled (after grace period)
        if (!$match->isActive()) {
            $event->cancel();
            return;
        }

        // 3. Anti-cheat: validate hit
        $validationResult = $this->hitValidator->validate($attacker, $victim);
        
        if (!$validationResult->isValid()) {
            // Cheat detected – block and flag player
            $event->cancel();
            $attacker->sendMessage("§c⚠ Hit flagged by anti-cheat!");
            return;
        }

        // 4. Get base damage from weapon (default 4.0 for unarmed)
        $baseDamage = 4.0; // Default unarmed damage
        
        // If weapon has attack damage property, use it
        $weapon = $attacker->getInventory()->getItemInHand();
        if (method_exists($weapon, 'getAttackDamage')) {
            $baseDamage = $weapon->getAttackDamage();
        }

        // 5. Collect hit statistics
        $statsSession = $this->statsCollector->getSession($attacker);
        if ($statsSession !== null) {
            $statsSession->recordSwing();
            $statsSession->recordHit(isCritical: false); // TODO: Check for critical hits
        }

        // 6. Apply knockback
        $this->knockbackEngine->applyKnockback($attacker, $victim, $baseDamage);

        // 7. Register damage exchange
        $this->statsCollector->handleDamageExchange($attacker, $victim, (int) $baseDamage);

        // 8. Set event damage to calculated value
        $event->setBaseDamage($baseDamage);
    }

    // -----------------------------------------------------------------------
    // Event: EntityDamage (ALL damage types)
    // -----------------------------------------------------------------------

    /**
     * Blocks all damage types other than player-to-player in a match.
     * We don't want fall damage, fire damage, starvation, etc.
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        if (!$entity instanceof Player) {
            return;
        }

        $uuid = $entity->getUniqueId()->toString();

        // If player is in a match, all other damage is blocked
        if ($this->matchManager->isInMatch($uuid)) {
            // Player-to-player damage is handled in onEntityDamageByEntity
            // Block everything else (fall, fire, starvation, etc)
            if (!($event instanceof EntityDamageByEntityEvent)) {
                $event->cancel();
            }
        }
    }

    // -----------------------------------------------------------------------
    // Event: PlayerDeath
    // -----------------------------------------------------------------------

    /**
     * Called when a player dies (HP drops to 0).
     * Ends the match – opponent wins.
     */
    public function onPlayerDeath(PlayerDeathEvent $event): void {
        $player = $event->getPlayer();
        $uuid = $player->getUniqueId()->toString();

        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match === null || !$match->isActive()) {
            return;
        }

        // Find opponent
        $opponentUuid = $match->getOpponentUuid($uuid);
        if ($opponentUuid === null) {
            return;
        }

        $killedProfile  = $this->sessionManager->getSessionByUuid($uuid);
        $killerProfile  = $this->sessionManager->getSessionByUuid($opponentUuid);

        if ($killedProfile === null || $killerProfile === null) {
            return;
        }

        // Register kill/death in statistics
        $opponentPlayer = $this->getPlayerByUuid($opponentUuid);
        if ($opponentPlayer !== null) {
            $this->statsCollector->handleKillDeathExchange($opponentPlayer, $player);
        }

        // End match – opponent wins
        $this->matchManager->endMatch(
            matchId:       $match->getMatchId(),
            winnerUuid:    $opponentUuid,
            winnerProfile: $killerProfile,
            loserProfile:  $killedProfile,
        );

        // Cancel standard death message
        $event->setDeathMessage("");
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Get a player from the server by UUID.
     */
    private function getPlayerByUuid(string $uuid): ?Player {
        foreach (\pocketmine\Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->getUniqueId()->toString() === $uuid) {
                return $player;
            }
        }
        return null;
    }
}
