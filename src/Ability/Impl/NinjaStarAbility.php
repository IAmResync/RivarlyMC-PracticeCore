<?php

declare(strict_types=1);

namespace Ability\Impl;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use Ability\AbilityInterface;

/**
 * NinjaStar — teleports the player to a random safe position on the arena.
 *
 * Rules:
 *   - On activation: picks a random position within the arena bounds
 *   - Teleports the player instantly (no delay)
 *   - Gives Speed II for 2 seconds after teleport (momentum window)
 *   - Cooldown: 20 seconds
 *
 * Arena bounds are approximated using the player's current world spawn
 * + a configurable radius. For full arena support, inject ArenaInstance
 * once NoMercy finishes ArenaPool.
 *
 * Item: Nether Star (rare-looking, fits the "ninja" theme)
 */
final class NinjaStarAbility implements AbilityInterface {

    private const COOLDOWN_SECONDS  = 20;
    private const TELEPORT_RADIUS   = 8.0;   // max blocks from center
    private const SPEED_DURATION    = 40;    // ticks (2 seconds)
    private const SPEED_LEVEL       = 1;     // Speed II (amplifier 1 = level 2)

    public function getId(): string           { return 'ninja_star'; }
    public function getDisplayName(): string  { return '§5Ninja Star'; }
    public function getCooldownSeconds(): int { return self::COOLDOWN_SECONDS; }

    public function getItem(): Item {
        $item = VanillaItems::NETHER_STAR()->setCount(1);
        $item->setCustomName('§5§lNinja Star');
        $item->setLore(['§7Right-click to teleport', '§7Disappear from your opponent!']);
        return $item;
    }

    public function getActivationMessage(): ?string {
        return '§5Ninja Star §7— teleported!';
    }

    // -----------------------------------------------------------------------
    // Execution
    // -----------------------------------------------------------------------

    public function execute(Player $player): void {
        $pos    = $player->getPosition();
        $world  = $player->getWorld();

        // Pick a random angle and distance within the arena radius
        $angle    = lcg_value() * M_PI * 2;
        $distance = lcg_value() * self::TELEPORT_RADIUS;

        $newX = $pos->getX() + cos($angle) * $distance;
        $newZ = $pos->getZ() + sin($angle) * $distance;

        // Find safe Y (highest solid block at that X/Z)
        $newY = $world->getHighestBlockAt((int) $newX, (int) $newZ);
        if ($newY === null) {
            // Fallback — stay at same Y if no solid block found
            $newY = (int) $pos->getY();
        }

        $destination = new \pocketmine\math\Vector3($newX, $newY + 1.0, $newZ);
        $player->teleport($destination);

        // Apply Speed II briefly so the teleport feels impactful
        $speedEffect = new \pocketmine\entity\effect\EffectInstance(
            \pocketmine\entity\effect\VanillaEffects::SPEED(),
            self::SPEED_DURATION,
            self::SPEED_LEVEL,
            false,
        );
        $player->getEffects()->add($speedEffect);
    }
}
