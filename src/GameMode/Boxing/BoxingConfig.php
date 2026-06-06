<?php

declare(strict_types=1);

namespace GameMode\Boxing;

/**
 * All tunable values for Boxing mode.
 * Pure PHP — no PocketMine imports, fully testable.
 */
final class BoxingConfig {

    public function __construct(
        // --- Match ---
        public readonly int   $matchDurationSeconds = 180,  // 3 min
        public readonly float $startingHealth        = 20.0,

        // --- Boxing rules ---
        public readonly int   $maxHits              = 100,  // first to 100 hits wins
        public readonly float $healthResetValue      = 20.0, // HP reset after every hit

        // --- Kit ---
        public readonly int   $swordSlot            = 0,
        /** @var array<string, int> */
        public readonly array $swordEnchants        = ['sharpness' => 1],

        // --- Display ---
        public readonly string $hitActionBarColor   = '§c', // red hit counter
        public readonly string $displayName         = '§6Boxing',
    ) {}
}