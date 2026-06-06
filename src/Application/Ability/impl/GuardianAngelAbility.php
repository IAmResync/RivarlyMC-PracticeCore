<?php

declare(strict_types=1);

namespace Application\Ability\impl;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use Application\Ability\AbilityInterface;

/**
 * Guardian Angel — one-time shield that absorbs the next incoming hit.
 *
 * Rules:
 * - Player activates → shield is armed (stored in $shielded map)
 * - Next time the player would take damage → hit is cancelled, shield consumed
 * - Shield does NOT persist between matches — cleared on match end
 * - Cooldown: 45 seconds (long because it's effectively a free hit)
 * - Shield is one-use only — after absorbing one hit it's gone
 *
 * AbilityListener::onEntityDamage() calls tryAbsorb($uuid) before
 * applying damage. If it returns true, the event is cancelled.
 *
 * This ability is stateful per-player (shields map) but the state
 * is minimal — just a boolean per uuid.
 */
final class GuardianAngelAbility implements AbilityInterface {

    private const COOLDOWN_SECONDS = 45;

    /** @var array<string, bool> uuid => shield armed */
    private array $shielded = [];

    // -----------------------------------------------------------------------
    // AbilityInterface
    // -----------------------------------------------------------------------

    public function getId(): string           { return 'guardian_angel'; }
    public function getDisplayName(): string  { return '§fGuardian Angel'; }
    public function getCooldownSeconds(): int { return self::COOLDOWN_SECONDS; }

    public function getItem(): Item {
        $item = VanillaItems::TOTEM()->setCount(1);
        $item->setCustomName('§f§lGuardian Angel');
        $item->setLore(['§7Right-click to arm your shield', '§7Absorbs the next hit']);
        return $item;
    }

    public function getActivationMessage(): ?string {
        return '§fGuardian Angel §7armed — your next hit will be absorbed!';
    }

    // -----------------------------------------------------------------------
    // Execution — arms the shield
    // -----------------------------------------------------------------------

    public function execute(Player $player): void {
        $uuid = $player->getUniqueId()->toString();
        $this->shielded[$uuid] = true;
    }

    // -----------------------------------------------------------------------
    // Hit absorption — called by AbilityListener::onEntityDamage()
    // -----------------------------------------------------------------------

    /**
     * Attempts to absorb an incoming hit for this player.
     * Returns true if shield was active and consumed (hit should be cancelled).
     * Returns false if no shield — damage proceeds normally.
     */
    public function tryAbsorb(string $uuid): bool {
        if (!($this->shielded[$uuid] ?? false)) {
            return false;
        }

        // Consume the shield — one use only
        unset($this->shielded[$uuid]);
        return true;
    }

    public function hasShield(string $uuid): bool {
        return $this->shielded[$uuid] ?? false;
    }

    // -----------------------------------------------------------------------
    // Cleanup
    // -----------------------------------------------------------------------

    public function clearPlayer(string $uuid): void {
        unset($this->shielded[$uuid]);
    }
}