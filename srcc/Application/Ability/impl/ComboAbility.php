<?php

declare(strict_types=1);

namespace Application\Ability\impl;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskScheduler;
use Application\Ability\AbilityInterface;

/**
 * Combo Ability — tracks hits landed during a 6-second window,
 * then rewards the player with Strength II for that many seconds.
 *
 * Mechanic (exactly like cPrac Combo.java):
 *   1. Player activates the ability (right-click blaze rod)
 *   2. For the next 6 seconds, every hit the player lands is counted
 *   3. After 6 seconds: apply Strength II for (hitCount) seconds
 *   4. If player lands 0 hits → nothing happens (wasted ability)
 *
 * The ability is stateful per-player (tracks active windows).
 * State lives here as a map — abilities are shared instances but
 * this is acceptable since the map is keyed by uuid.
 *
 * AbilityListener handles cooldowns — this class only handles the effect.
 */
final class ComboAbility implements AbilityInterface, Listener {

    private const TRACKING_WINDOW_SECONDS = 6;
    private const STRENGTH_LEVEL          = 2; // Strength II

    /** @var array<string, int> uuid => hits landed during active window */
    private array $activeWindows = [];

    public function __construct(
        private readonly TaskScheduler $scheduler,
    ) {}

    // -----------------------------------------------------------------------
    // AbilityInterface
    // -----------------------------------------------------------------------

    public function getId(): string          { return 'combo'; }
    public function getDisplayName(): string { return '§dCombo'; }
    public function getCooldownSeconds(): int { return 20; }

    public function getItem(): Item {
        return VanillaItems::BLAZE_ROD()->setCustomName('§dCombo §7(right-click)');
    }

    public function getActivationMessage(): ?string {
        return '§dCombo §7activated! Hit as many times as you can in §f6s§7.';
    }

    /**
     * Starts the 6-second tracking window for this player.
     * After the window closes, applies Strength II for hitCount seconds.
     */
    public function execute(Player $player): void {
        $uuid = $player->getUniqueId()->toString();

        // Start fresh tracking window
        $this->activeWindows[$uuid] = 0;

        // After 6 seconds: apply strength based on hit count
        $this->scheduler->scheduleDelayedTask(
            new ClosureTask(function () use ($uuid, $player): void {
                $hits = $this->activeWindows[$uuid] ?? 0;
                unset($this->activeWindows[$uuid]);

                if (!$player->isOnline() || $hits === 0) {
                    return;
                }

                $duration  = $hits * 20; // ticks (1 hit = 1 second = 20 ticks)
                $effect    = new EffectInstance(
                    VanillaEffects::STRENGTH(),
                    $duration,
                    self::STRENGTH_LEVEL - 1, // amplifier is level - 1
                    false,
                );

                $player->getEffects()->add($effect);
                $player->sendActionBarMessage(
                    "§dCombo §7— §fStrength II §7for §f{$hits}s §7({$hits} hits)"
                );
            }),
            self::TRACKING_WINDOW_SECONDS * 20
        );
    }

    // -----------------------------------------------------------------------
    // Hit counting — called from CombatListener (or register as Listener)
    // -----------------------------------------------------------------------

    /**
     * Register this as a Listener and hook into damage events to count hits.
     * Only counts hits during an active window.
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $attacker = $event->getDamager();
        if (!$attacker instanceof Player) return;

        $uuid = $attacker->getUniqueId()->toString();

        if (!isset($this->activeWindows[$uuid])) return;

        $this->activeWindows[$uuid]++;

        // Live feedback during tracking window
        $hits = $this->activeWindows[$uuid];
        $attacker->sendActionBarMessage(
            "§dCombo §7— §f{$hits} hit" . ($hits === 1 ? '' : 's') . " §7(tracking...)"
        );
    }

    // -----------------------------------------------------------------------
    // Cleanup
    // -----------------------------------------------------------------------

    public function clearPlayer(string $uuid): void {
        unset($this->activeWindows[$uuid]);
    }

    public function isTracking(string $uuid): bool {
        return isset($this->activeWindows[$uuid]);
    }
}