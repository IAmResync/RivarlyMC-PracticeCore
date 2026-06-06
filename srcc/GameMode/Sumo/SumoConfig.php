<?php

declare(strict_types=1);

namespace GameMode\Sumo;

/**
 * All tunable values for Sumo mode.
 * Pure PHP — no PocketMine imports, fully testable.
 */
final class SumoConfig {

    public function __construct(
        // --- Match ---
        public readonly int   $matchDurationSeconds  = 300,   // 5 min
        public readonly float $startingHealth         = 20.0,

        // --- Sumo rules ---
        public readonly bool  $damageEnabled          = false, // no HP damage in Sumo
        public readonly float $knockbackMultiplier     = 1.8,  // stronger kb than default

        // --- Kit ---
        public readonly int   $swordSlot             = 0,
        /** @var array<string, int> */
        public readonly array $swordEnchants         = [],    // no enchants — pure knockback

        // --- Void detection ---
        // Player loses when they fall below this Y level
        public readonly float $voidYThreshold         = 0.0,

        // --- Display ---
        public readonly string $displayName           = '§aSumo',
    ) {}
}