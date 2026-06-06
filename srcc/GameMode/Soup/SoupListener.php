<?php

declare(strict_types=1);

namespace GameMode\Soup;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use Application\Match\MatchManager;

/**
 * Handles Soup-specific healing logic.
 *
 * On PlayerItemConsumeEvent with mushroom stew:
 *   1. Cancel default consume behaviour
 *   2. Heal player by SoupMode::getHealAmount() (capped at max HP)
 *   3. Replace stew with empty bowl in the same slot
 */
final class SoupListener implements Listener {

    public function __construct(
        private readonly SoupMode     $mode,
        private readonly MatchManager $matchManager,
        private readonly mixed        $isSoupPlayer, // callable(string $uuid): bool
    ) {}

    public function onConsume(PlayerItemConsumeEvent $event): void {
        $player = $event->getPlayer();
        $item   = $event->getItem();
        $uuid   = $player->getUniqueId()->toString();

        if (!($this->isSoupPlayer)($uuid)) return;
        if ($item->getTypeId() !== VanillaItems::MUSHROOM_STEW()->getTypeId()) return;

        $event->cancel();

        // Heal
        $maxHp  = $player->getMaxHealth();
        $newHp  = min($maxHp, $player->getHealth() + $this->mode->getHealAmount());
        $player->setHealth($newHp);

        // Replace stew with empty bowl in held slot
        $inv  = $player->getInventory();
        $slot = $inv->getHeldItemIndex();
        $inv->setItem($slot, VanillaItems::BOWL()->setCount(1));

        $player->sendActionBarMessage('§aHealed §f+' . $this->mode->getHealAmount() . ' HP');
    }
}
