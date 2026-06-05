<?php

declare(strict_types=1);

namespace Listener;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\player\Player;
use Application\Match\MatchManager;
use Application\Player\StatsCollector;

/**
 * Listens to raw network packets sent by client.
 * Used for:
 *   1. Registration of swing/attack animations (AnimatePacket)
 *   2. Detection of actions not available in standard API
 *   3. Optimization and anti-cheat (CPS limiter pattern matching)
 *
 * In Bedrock Edition there is limited access to packet-level events,
 * but we can handle AnimatePacket for swing count statistics.
 *
 * Registration (Bootstrap):
 *   $pm->registerEvents(new PacketListener($matchManager, $statsCollector), $plugin);
 */
final class PacketListener implements Listener {

    public function __construct(
        private readonly MatchManager   $matchManager,
        private readonly StatsCollector $statsCollector,
    ) {}

    // -----------------------------------------------------------------------
    // Registration of swing/attack animations
    // -----------------------------------------------------------------------

    /**
     * Called on every DataPacketReceiveEvent.
     * Check if it's an AnimatePacket and register swing.
     */
    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();

        if ($player === null) {
            return;
        }

        $uuid = $player->getUniqueId()->toString();

        // Check if player is in a match
        if (!$this->matchManager->isInMatch($uuid)) {
            return;
        }

        // AnimatePacket – contains swing/attack message
        if ($packet instanceof AnimatePacket) {
            $this->handleAnimatePacket($player, $packet);
        }

        // InventoryTransactionPacket – can be used to analyze items
        // and block certain actions if needed
        if ($packet instanceof InventoryTransactionPacket) {
            $this->handleInventoryTransaction($player, $packet);
        }
    }

    // -----------------------------------------------------------------------
    // Handlers for specific packet types
    // -----------------------------------------------------------------------

    /**
     * Handle AnimatePacket.
     * Typical action IDs:
     *   0 = swing/no damage swing
     *   1 = wake up (from sleep)
     *   2 = critical hit
     *   3 = magic critical hit
     */
    private function handleAnimatePacket(Player $player, AnimatePacket $packet): void {
        // AnimatePacket::ACTION_SWING_ARM = 0
        if ($packet->action === AnimatePacket::ACTION_SWING_ARM) {
            // Register swing in statistics
            $this->statsCollector->handleSwing($player);
        }
    }

    /**
     * Handle InventoryTransactionPacket.
     * Useful for monitoring:
     *   1. Whether player uses items (potions, Golden Apples)
     *   2. Whether player cheats (double click, item duping)
     *
     * Can be extended with anti-cheat logic in the future.
     */
    private function handleInventoryTransaction(Player $player, InventoryTransactionPacket $packet): void {
        // Register inventory transactions (pickup items, use items)
        // Currently no special logic – can be extended in future

        // If we want to block certain items or actions:
        // $packet->cancelled = true;

        // For now we allow everything during a match
    }
}
