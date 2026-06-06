<?php

declare(strict_types=1);

namespace Presentation\Leaderboard;

use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\Server;
use Infrastructure\Cache\LeaderboardCache;
use Presentation\Nametag\NametagRenderer;

/**
 * Renders a physical hologram leaderboard in the lobby world.
 * Each leaderboard is a vertical stack of floating text entities (ArmorStand or Text).
 *
 * One HologramLeaderboard instance = one physical hologram in the world.
 * HologramTask refreshes all registered holograms every 30 seconds.
 *
 * Usage in Plugin::onEnable():
 *   $hologram = new HologramLeaderboard(
 *       title:    '§6§lTop 10 — Global ELO',
 *       position: new Vector3(0, 65, 0),
 *       world:    $server->getWorldManager()->getWorldByName('lobby'),
 *       cache:    $leaderboardCache,
 *       renderer: $nametagRenderer,
 *   );
 *   $hologram->spawn();
 *
 * The hologram spawns one text entity per line (title + up to 10 rows).
 * Each entity is invisible except for its nametag — standard PMMP hologram trick.
 */
final class HologramLeaderboard {

    /** Vertical gap between hologram lines in blocks */
    private const LINE_GAP = 0.28;

    /** @var array<int, int> line index => entity runtime ID */
    private array $entityIds = [];

    private bool $spawned = false;

    public function __construct(
        private readonly string          $title,
        private readonly Vector3         $position,
        private readonly World           $world,
        private readonly LeaderboardCache $cache,
        private readonly NametagRenderer  $renderer,
        private readonly string          $gameMode = 'global',
        private readonly int             $lines    = 10,
    ) {}

    // -----------------------------------------------------------------------
    // Spawn / despawn
    // -----------------------------------------------------------------------

    /**
     * Spawns all hologram lines at the configured position.
     * Lines are stacked upward from position.y (title at the top).
     */
    public function spawn(): void {
        if ($this->spawned) {
            $this->despawn();
        }

        // Title line (index 0 = highest)
        $this->spawnLine(0, $this->title);

        // Placeholder rows while cache loads
        for ($i = 1; $i <= $this->lines; $i++) {
            $this->spawnLine($i, '§8Loading...');
        }

        $this->spawned = true;
        $this->refresh();
    }

    /**
     * Removes all entities from the world.
     */
    public function despawn(): void {
        foreach ($this->entityIds as $index => $runtimeId) {
            $pk = new RemoveActorPacket();
            $pk->actorUniqueId = $runtimeId;

            foreach ($this->world->getPlayers() as $player) {
                $player->getNetworkSession()->sendDataPacket($pk);
            }
        }

        $this->entityIds = [];
        $this->spawned   = false;
    }

    // -----------------------------------------------------------------------
    // Refresh data
    // -----------------------------------------------------------------------

    /**
     * Pulls fresh data from LeaderboardCache and updates hologram text.
     * Called by HologramTask every 30 seconds.
     */
    public function refresh(): void {
        if (!$this->spawned) return;

        $topPlayers = $this->cache->getTopPlayers($this->lines);

        $position = 1;
        foreach ($topPlayers as $playerName => $elo) {
            $posColor = match($position) {
                1       => '§6',
                2       => '§f',
                3       => '§c',
                default => '§7',
            };

            $line = "{$posColor}#{$position} §f{$playerName} §8— {$posColor}{$elo} ELO";
            $this->updateLine($position, $line);
            $position++;
        }

        // Fill remaining slots if fewer than $lines players
        for ($i = $position; $i <= $this->lines; $i++) {
            $this->updateLine($i, '§8---');
        }
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Spawns a single floating text entity at the correct Y offset.
     * Uses AddActorPacket with a Slime (size 0) as the invisible carrier.
     * The nametag is set via EntityMetadataProperties::NAMETAG.
     */
    private function spawnLine(int $index, string $text): void {
        $y = $this->position->y + ($this->lines - $index) * self::LINE_GAP;
        $pos = new Vector3($this->position->x, $y, $this->position->z);

        $runtimeId = mt_rand(1_000_000, PHP_INT_MAX);
        $this->entityIds[$index] = $runtimeId;

        $metadata = new EntityMetadataCollection();
        $metadata->setString(EntityMetadataProperties::NAMETAG, $text);
        $metadata->setByte(EntityMetadataProperties::ALWAYS_SHOW_NAMETAG, 1);
        $metadata->setGenericFlag(EntityMetadataFlags::INVISIBLE, true);
        $metadata->setGenericFlag(EntityMetadataFlags::NO_AI, true);
        $metadata->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);
        $metadata->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);

        $pk = AddActorPacket::create(
            actorUniqueId:  $runtimeId,
            actorRuntimeId: $runtimeId,
            type:           EntityIds::SLIME,
            position:       $pos,
            motion:         new Vector3(0, 0, 0),
            pitch:          0.0,
            yaw:            0.0,
            headYaw:        0.0,
            bodyYaw:        0.0,
            attributes:     [],
            metadata:       $metadata->getAll(),
            links:          [],
        );

        foreach ($this->world->getPlayers() as $player) {
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    /**
     * Updates the text of an existing hologram line without respawning the entity.
     */
    private function updateLine(int $index, string $text): void {
        $runtimeId = $this->entityIds[$index] ?? null;
        if ($runtimeId === null) return;

        $metadata = new EntityMetadataCollection();
        $metadata->setString(EntityMetadataProperties::NAMETAG, $text);

        $pk = SetActorDataPacket::create(
            actorRuntimeId: $runtimeId,
            metadata:       $metadata->getAll(),
            syncedProperties: new \pocketmine\network\mcpe\protocol\types\entity\PropertySyncData([], []),
            tick:           0,
        );

        foreach ($this->world->getPlayers() as $player) {
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    // -----------------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------------

    public function isSpawned(): bool  { return $this->spawned; }
    public function getTitle(): string { return $this->title; }
    public function getGameMode(): string { return $this->gameMode; }
    public function getPosition(): Vector3 { return $this->position; }
}
