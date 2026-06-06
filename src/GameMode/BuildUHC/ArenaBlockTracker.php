<?php

declare(strict_types=1);

namespace GameMode\BuildUHC;

use pocketmine\world\Position;

/**
 * Tracks all blocks placed during a BuildUHC match.
 * After the match ends, all tracked blocks are removed from the world.
 *
 * One tracker per match. Owned by BuildUHCMode.
 * BuildUHCListener calls recordPlace() on BlockPlaceEvent.
 * MatchManager calls cleanup() on match end.
 */
final class ArenaBlockTracker {

    /** @var list<array{x:int,y:int,z:int,world:string}> */
    private array $placedBlocks = [];

    public function recordPlace(Position $position): void {
        $this->placedBlocks[] = [
            'x'     => $position->getFloorX(),
            'y'     => $position->getFloorY(),
            'z'     => $position->getFloorZ(),
            'world' => $position->getWorld()->getFolderName(),
        ];
    }

    /**
     * Removes all placed blocks from the world.
     * Called by MatchManager when the match ends.
     */
    public function cleanup(\pocketmine\world\World $world): void {
        foreach ($this->placedBlocks as $block) {
            if ($world->getFolderName() !== $block['world']) continue;
            $world->setBlock(
                new \pocketmine\math\Vector3($block['x'], $block['y'], $block['z']),
                \pocketmine\block\VanillaBlocks::AIR(),
                false
            );
        }
        $this->placedBlocks = [];
    }

    public function getCount(): int { return count($this->placedBlocks); }
}
