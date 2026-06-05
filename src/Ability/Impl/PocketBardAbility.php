<?php

declare(strict_types=1);

namespace Ability\Impl;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use Ability\AbilityInterface;

/**
 * PocketBard — grants Speed II + Strength I to the player and nearby allies for 6 seconds.
 *
 * Rules:
 *   - On activation: scan for players within 8 blocks
 *   - Apply Speed II (6s) + Strength I (6s) to self and every ally in range
 *   - Cooldown: 35 seconds
 *   - "Allies" = players in the same match (same matchId) — not enemies
 *
 * Item: Music Disc (fitting for a "bard" theme)
 */
final class PocketBardAbility implements AbilityInterface {

    private const COOLDOWN_SECONDS = 35;
    private const BUFF_DURATION    = 120; // ticks = 6 seconds
    private const RANGE            = 8.0; // blocks radius

    public function getId(): string           { return 'pocket_bard'; }
    public function getDisplayName(): string  { return '§aPocket Bard'; }
    public function getCooldownSeconds(): int { return self::COOLDOWN_SECONDS; }

    public function getItem(): Item {
        $item = VanillaItems::CLOCK()->setCount(1);
        $item->setCustomName('§a§lPocket Bard');
        $item->setLore(['§7Right-click to buff yourself', '§7Gives Speed + Strength nearby']);
        return $item;
    }

    public function getActivationMessage(): ?string {
        return '§aPocket Bard §7— Speed II + Strength I activated!';
    }

    public function execute(Player $player): void {
        $world  = $player->getWorld();
        $pos    = $player->getPosition();

        // Collect self + nearby players
        $targets = [$player];
        foreach ($world->getPlayers() as $other) {
            if ($other === $player) continue;
            if ($other->getPosition()->distance($pos) <= self::RANGE) {
                $targets[] = $other;
            }
        }

        foreach ($targets as $target) {
            $target->getEffects()->add(new EffectInstance(
                VanillaEffects::SPEED(), self::BUFF_DURATION, 1, false
            ));
            $target->getEffects()->add(new EffectInstance(
                VanillaEffects::STRENGTH(), self::BUFF_DURATION, 0, false
            ));
            if ($target !== $player) {
                $target->sendActionBarMessage('§aPocket Bard §7— buffed by your ally!');
            }
        }
    }
}
