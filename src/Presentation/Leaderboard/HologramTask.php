<?php

declare(strict_types=1);

namespace Presentation\Leaderboard;

use pocketmine\scheduler\Task;

/**
 * Repeating task that refreshes all registered HologramLeaderboards every 30 seconds.
 *
 * Why 30 seconds and not real-time?
 *   - Hologram refresh sends packets to all world players — expensive at scale.
 *   - LeaderboardCache itself updates per ELO change (after every match).
 *   - 30 seconds is imperceptible to players but eliminates unnecessary packet spam.
 *
 * Registration in Bootstrap::register():
 *   $plugin->getScheduler()->scheduleRepeatingTask(
 *       new HologramTask($holograms),
 *       600 // 30 seconds = 600 ticks
 *   );
 *
 * Usage:
 *   // Create hologram and register it
 *   $task = new HologramTask();
 *   $task->register($hologram);
 *
 *   // Or pass in constructor
 *   $task = new HologramTask([$hologram1, $hologram2]);
 */
final class HologramTask extends Task {

    /** @var list<HologramLeaderboard> */
    private array $holograms;

    /**
     * @param list<HologramLeaderboard> $holograms  Pre-registered holograms (optional).
     */
    public function __construct(array $holograms = []) {
        $this->holograms = $holograms;
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    /**
     * Adds a hologram to the refresh cycle.
     * Call this after spawning a hologram in the world.
     */
    public function register(HologramLeaderboard $hologram): void {
        $this->holograms[] = $hologram;
    }

    /**
     * Removes a hologram from the refresh cycle (e.g. world unload).
     * Does NOT despawn the hologram — call $hologram->despawn() separately.
     */
    public function unregister(HologramLeaderboard $hologram): void {
        $this->holograms = array_values(
            array_filter($this->holograms, fn($h) => $h !== $hologram)
        );
    }

    /**
     * Returns all currently tracked holograms.
     *
     * @return list<HologramLeaderboard>
     */
    public function getAll(): array {
        return $this->holograms;
    }

    // -----------------------------------------------------------------------
    // Tick
    // -----------------------------------------------------------------------

    public function onRun(): void {
        foreach ($this->holograms as $hologram) {
            if (!$hologram->isSpawned()) continue;
            $hologram->refresh();
        }
    }
}
