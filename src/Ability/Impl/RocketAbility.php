<?php

declare(strict_types=1);

namespace Ability\Impl;

use pocketmine\entity\projectile\Snowball;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use Ability\AbilityInterface;

/**
 * Rocket — launches an explosive snowball that knocks back the opponent on hit.
 *
 * Rules:
 *   - On activation: fires a snowball in the direction the player is looking
 *   - On hit: applies a strong knockback burst to the victim (no damage)
 *   - Snowball is tagged so ProjectileHitEntityEvent can identify it as a Rocket
 *   - Cooldown: 25 seconds
 *
 * The knockback on hit is handled by RocketProjectileListener (registered separately)
 * which checks if the hit snowball is tagged as a Rocket and applies the burst.
 *
 * This ability itself only handles launch — hit logic lives in the listener
 * to keep this class clean and testable.
 *
 * Item: Fire Charge (visually fitting for a rocket)
 */
final class RocketAbility implements AbilityInterface {

    private const COOLDOWN_SECONDS    = 25;
    private const LAUNCH_SPEED        = 2.5;   // blocks per tick
    private const KNOCKBACK_INTENSITY = 3.0;   // multiplier for burst

    /** @var array<int, string> entity id => attacker uuid (for hit attribution) */
    private array $taggedProjectiles = [];

    public function getId(): string           { return 'rocket'; }
    public function getDisplayName(): string  { return '§cRocket'; }
    public function getCooldownSeconds(): int { return self::COOLDOWN_SECONDS; }

    public function getItem(): Item {
        $item = VanillaItems::FIRE_CHARGE()->setCount(1);
        $item->setCustomName('§c§lRocket');
        $item->setLore(['§7Right-click to launch', '§7Knock your opponent away!']);
        return $item;
    }

    public function getActivationMessage(): ?string {
        return '§cRocket §7launched!';
    }

    // -----------------------------------------------------------------------
    // Execution — launch snowball
    // -----------------------------------------------------------------------

    public function execute(Player $player): void {
        $location = $player->getLocation();
        $world    = $player->getWorld();

        // Direction the player is looking
        $dirVec = $location->getDirectionVector()->multiply(self::LAUNCH_SPEED);

        // Spawn snowball slightly in front of the player (not inside them)
        $spawnPos = $player->getEyePos()->add(
            $location->getDirectionVector()->x,
            $location->getDirectionVector()->y,
            $location->getDirectionVector()->z,
        );

        $nbt = \pocketmine\nbt\tag\CompoundTag::create()
            ->setString('RocketOwner', $player->getUniqueId()->toString());

        $snowball = new Snowball(
            \pocketmine\world\Position::fromObject($spawnPos, $world),
            null,
            $nbt,
        );
        $snowball->setOwningEntity($player);
        $snowball->setMotion($dirVec);
        $world->addEntity($snowball);

        // Tag for hit detection
        $this->taggedProjectiles[$snowball->getId()] = $player->getUniqueId()->toString();
    }

    // -----------------------------------------------------------------------
    // Hit detection — called by AbilityListener on ProjectileHitEntityEvent
    // -----------------------------------------------------------------------

    /**
     * Checks if the projectile is a tagged Rocket and applies knockback burst.
     * Returns true if handled (event should be cancelled for normal damage).
     */
    public function handleProjectileHit(ProjectileHitEntityEvent $event): bool {
        $projectile = $event->getEntity();
        $hitEntity  = $event->getEntityHit();

        if (!$hitEntity instanceof Player) return false;
        if (!isset($this->taggedProjectiles[$projectile->getId()])) return false;

        // Clean up tag
        unset($this->taggedProjectiles[$projectile->getId()]);

        // Apply knockback burst — push victim away from impact point
        $direction = $hitEntity->getPosition()
            ->subtractVector($projectile->getPosition())
            ->normalize();

        $burst = new Vector3(
            $direction->x * self::KNOCKBACK_INTENSITY,
            0.6,
            $direction->z * self::KNOCKBACK_INTENSITY,
        );

        $hitEntity->setMotion($burst);
        $hitEntity->sendActionBarMessage('§cYou were hit by a Rocket!');

        return true;
    }

    public function isTagged(int $entityId): bool {
        return isset($this->taggedProjectiles[$entityId]);
    }

    public function clearPlayer(string $uuid): void {
        $this->taggedProjectiles = array_filter(
            $this->taggedProjectiles,
            fn(string $ownerUuid) => $ownerUuid !== $uuid
        );
    }
}
