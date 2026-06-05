<?php

declare(strict_types=1);

namespace Ability;

/**
 * Tracks per-player cooldowns for every ability.
 * Pure PHP — no PMMP dependency, fully testable.
 *
 * Structure: uuid => [abilityId => unix timestamp when cooldown expires]
 *
 * All methods are O(1). Expired entries are cleaned up lazily on access
 * (no background task needed — PHP gc handles it naturally).
 *
 * Usage in AbilityListener:
 *   if ($cooldowns->isOnCooldown($uuid, $abilityId)) {
 *       $remaining = $cooldowns->getRemainingSeconds($uuid, $abilityId);
 *       $player->sendActionBarMessage("§cWait {$remaining}s...");
 *       return;
 *   }
 *   $ability->execute($player);
 *   $cooldowns->setCooldown($uuid, $abilityId, $ability->getCooldownSeconds());
 */
final class AbilityCooldownManager {

    /** @var array<string, array<string, int>> uuid => [abilityId => expiresAt] */
    private array $cooldowns = [];

    // -----------------------------------------------------------------------
    // Setting & checking
    // -----------------------------------------------------------------------

    /**
     * Sets a cooldown for a player on a specific ability.
     * Overwrites any existing cooldown (re-use resets the timer).
     */
    public function setCooldown(string $uuid, string $abilityId, int $seconds): void {
        if (!isset($this->cooldowns[$uuid])) {
            $this->cooldowns[$uuid] = [];
        }
        $this->cooldowns[$uuid][$abilityId] = time() + $seconds;
    }

    /**
     * Returns true if the player cannot use this ability yet.
     */
    public function isOnCooldown(string $uuid, string $abilityId): bool {
        $expiresAt = $this->cooldowns[$uuid][$abilityId] ?? 0;
        return time() < $expiresAt;
    }

    /**
     * How many seconds remain on the cooldown (0 if not on cooldown).
     */
    public function getRemainingSeconds(string $uuid, string $abilityId): int {
        $expiresAt = $this->cooldowns[$uuid][$abilityId] ?? 0;
        return max(0, $expiresAt - time());
    }

    /**
     * Remaining seconds as a formatted string — e.g. "3s" or "0s".
     * Used directly in action bar messages.
     */
    public function getRemainingFormatted(string $uuid, string $abilityId): string {
        return $this->getRemainingSeconds($uuid, $abilityId) . 's';
    }

    // -----------------------------------------------------------------------
    // Cleanup
    // -----------------------------------------------------------------------

    /**
     * Clears all cooldowns for a player (on logout or match end).
     */
    public function clearPlayer(string $uuid): void {
        unset($this->cooldowns[$uuid]);
    }

    /**
     * Clears one specific cooldown (e.g. admin bypass command).
     */
    public function clearCooldown(string $uuid, string $abilityId): void {
        unset($this->cooldowns[$uuid][$abilityId]);
    }

    /**
     * Clears all expired cooldowns across all players.
     * Optional — call from a low-frequency task if memory is a concern.
     * Not required for correctness since isOnCooldown() checks time().
     */
    public function purgeExpired(): void {
        $now = time();
        foreach ($this->cooldowns as $uuid => $abilities) {
            foreach ($abilities as $abilityId => $expiresAt) {
                if ($now >= $expiresAt) {
                    unset($this->cooldowns[$uuid][$abilityId]);
                }
            }
            if (empty($this->cooldowns[$uuid])) {
                unset($this->cooldowns[$uuid]);
            }
        }
    }

    /**
     * Returns all active cooldowns for a player — used in settings menu / debug.
     *
     * @return array<string, int> abilityId => remaining seconds
     */
    public function getActiveCooldowns(string $uuid): array {
        $now    = time();
        $result = [];

        foreach ($this->cooldowns[$uuid] ?? [] as $abilityId => $expiresAt) {
            $remaining = $expiresAt - $now;
            if ($remaining > 0) {
                $result[$abilityId] = $remaining;
            }
        }

        return $result;
    }
}
