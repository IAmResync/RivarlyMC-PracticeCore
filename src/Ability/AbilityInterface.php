<?php

declare(strict_types=1);

namespace Ability;

use pocketmine\item\Item;
use pocketmine\player\Player;

/**
 * Contract that every partner item / ability must implement.
 *
 * Each ability is a self-contained class responsible for:
 *   - Providing its Item (what the player holds / clicks with)
 *   - Defining its cooldown in seconds
 *   - Executing its effect when the player activates it
 *
 * Abilities are stateless — all mutable state (cooldowns, active buffs)
 * lives in AbilityCooldownManager, not in the ability itself.
 * This means one ability instance can be shared across all players.
 *
 * Lifecycle (handled by AbilityListener):
 *   1. Player right-clicks / interacts with the ability item
 *   2. AbilityListener calls AbilityRegistry::findByItem($item)
 *   3. If found: check AbilityCooldownManager::isOnCooldown($uuid, $abilityId)
 *   4. If not on cooldown: ability->execute($player) + setCooldown(...)
 *   5. Send cooldown action bar message to player
 */
interface AbilityInterface {

    /**
     * Unique internal identifier — used as cooldown key.
     * Must be lowercase, no spaces (e.g. "combo", "ninja_star", "rocket").
     */
    public function getId(): string;

    /**
     * Display name shown to players in action bar / chat.
     * May include color codes (e.g. "§dCombo").
     */
    public function getDisplayName(): string;

    /**
     * The item a player must hold and right-click to activate this ability.
     * AbilityRegistry uses this for item matching.
     */
    public function getItem(): Item;

    /**
     * How many seconds the player must wait before using this ability again.
     */
    public function getCooldownSeconds(): int;

    /**
     * Executes the ability effect on the given player.
     * Called by AbilityListener after cooldown check passes.
     *
     * The ability receives the full Player object — it can apply effects,
     * launch projectiles, teleport, etc.
     * It must NOT set its own cooldown — AbilityListener does that.
     */
    public function execute(Player $player): void;

    /**
     * Optional: message shown in action bar when ability activates.
     * Return null to show nothing.
     */
    public function getActivationMessage(): ?string;
}
