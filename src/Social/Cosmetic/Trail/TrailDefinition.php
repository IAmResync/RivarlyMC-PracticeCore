<?php

declare(strict_types=1);

namespace Social\Cosmetic\Trail;

/**
 * Immutable definition of a particle trail cosmetic.
 * Pure PHP — no PocketMine dependency.
 *
 * Each trail has a unique id, display name, particle type string,
 * and the interval (in ticks) at which particles spawn.
 *
 * TrailRegistry holds all registered trails.
 * TrailTask reads the active trail per player and spawns particles.
 *
 * Particle type strings map to PMMP particle classes:
 *   'flame'   → FlameParticle
 *   'heart'   → HeartParticle
 *   'smoke'   → SmokeParticle
 *   'lava'    → LavaParticle
 *   'explode' → ExplodeParticle
 *   'portal'  → PortalParticle
 *
 * Usage:
 *   $trail = new TrailDefinition('flame', '§cFlame Trail', 'flame', 3);
 *   $registry->register($trail);
 */
final class TrailDefinition {

    public function __construct(
        private readonly string $id,           // unique key e.g. "flame"
        private readonly string $displayName,  // shown in cosmetics menu
        private readonly string $particleType, // maps to particle class in TrailTask
        private readonly int    $intervalTicks = 4,  // how often particles spawn
        private readonly string $unlockKey     = '',  // empty = default unlocked
    ) {}

    public function getId(): string           { return $this->id; }
    public function getDisplayName(): string  { return $this->displayName; }
    public function getParticleType(): string { return $this->particleType; }
    public function getIntervalTicks(): int   { return $this->intervalTicks; }
    public function getUnlockKey(): string    { return $this->unlockKey; }

    /**
     * Whether this trail requires explicit unlock or is available to everyone.
     */
    public function isDefaultUnlocked(): bool {
        return $this->unlockKey === '';
    }
}
