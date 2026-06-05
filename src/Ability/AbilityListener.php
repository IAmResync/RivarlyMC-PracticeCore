<?php

declare(strict_types=1);

namespace Ability;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use Application\Match\MatchManager;
use Application\Player\SessionManager;

/**
 * Handles all ability activation events.
 *
 * Flow on every PlayerItemUseEvent:
 *   1. Check player is in an active match (abilities only work in-match)
 *   2. Find ability by held item via AbilityRegistry::findByItem()
 *   3. Check cooldown via AbilityCooldownManager::isOnCooldown()
 *   4. If on cooldown → send action bar with remaining time, return
 *   5. Execute ability, set cooldown, send activation message
 *
 * Registration in Plugin::onEnable():
 *   $plugin->getServer()->getPluginManager()->registerEvents(
 *       new AbilityListener($registry, $cooldowns, $matchManager, $sessionManager),
 *       $plugin
 *   );
 */
final class AbilityListener implements Listener {

    public function __construct(
        private readonly AbilityRegistry        $registry,
        private readonly AbilityCooldownManager $cooldowns,
        private readonly MatchManager            $matchManager,
        private readonly SessionManager          $sessionManager,
    ) {}

    // -----------------------------------------------------------------------
    // Item use — main ability trigger
    // -----------------------------------------------------------------------

    public function onItemUse(PlayerItemUseEvent $event): void {
        $player = $event->getPlayer();
        $item   = $player->getInventory()->getItemInHand();
        $uuid   = $player->getUniqueId()->toString();

        // Abilities only work while in an active match
        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match === null || !$match->isActive()) {
            return;
        }

        // Check if held item matches any registered ability
        $ability = $this->registry->findByItem($item);
        if ($ability === null) {
            return;
        }

        $abilityId = $ability->getId();

        // On cooldown — show remaining time and block the use
        if ($this->cooldowns->isOnCooldown($uuid, $abilityId)) {
            $remaining = $this->cooldowns->getRemainingFormatted($uuid, $abilityId);
            $player->sendActionBarMessage(
                "§c{$ability->getDisplayName()} §7— cooldown: §f{$remaining}"
            );
            $event->cancel();
            return;
        }

        // Execute ability effect
        $ability->execute($player);

        // Set cooldown after execution
        $this->cooldowns->setCooldown($uuid, $abilityId, $ability->getCooldownSeconds());

        // Send activation message if defined
        $msg = $ability->getActivationMessage();
        if ($msg !== null) {
            $player->sendActionBarMessage($msg);
        }

        // Cancel default item use (eating, placing, etc.)
        $event->cancel();
    }

    // -----------------------------------------------------------------------
    // Cleanup on quit
    // -----------------------------------------------------------------------

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $uuid = $event->getPlayer()->getUniqueId()->toString();
        $this->cooldowns->clearPlayer($uuid);
    }
}
