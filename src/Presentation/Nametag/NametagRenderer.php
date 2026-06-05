<?php

declare(strict_types=1);

namespace Presentation\Nametag;

use Domain\Player\Division;
use Domain\Player\PlayerProfile;

/**
 * Builds the nametag string shown above a player's head.
 * Pure PHP — no PocketMine dependency, fully testable.
 *
 * Output format (above head):
 *   §7[§bDiamond§7] §fResync §8(1624)
 *
 * Compact format (tab list / hologram):
 *   §b[Diamond] §fResync
 *
 * Colors come directly from Division::getColor() — single source of truth.
 * The renderer never sends packets — that is NametagTask's responsibility.
 */
final class NametagRenderer {

    /**
     * Full nametag shown above player's head.
     * Includes division badge, name, and ELO in parentheses.
     */
    public function render(PlayerProfile $profile): string {
        $division = $profile->getDivision();
        $color    = $division->getColor();
        $badge    = $division->getDisplayName(); // already colored e.g. "§bDiamond"
        $name     = $profile->getName();
        $elo      = $profile->getGlobalElo();

        return "§7[{$badge}§7] §f{$name} §8({$elo})";
    }

    /**
     * Compact line for tab list and hologram leaderboards.
     * No ELO number, just badge + name.
     */
    public function renderCompact(PlayerProfile $profile): string {
        $badge = $profile->getDivision()->getDisplayName();
        return "§7[{$badge}§7] §f{$profile->getName()}";
    }

    /**
     * Just the colored division prefix — e.g. "§7[§bDiamond§7]".
     * Used as a tab list prefix next to ping.
     */
    public function renderBadge(PlayerProfile $profile): string {
        $badge = $profile->getDivision()->getDisplayName();
        return "§7[{$badge}§7]";
    }

    /**
     * Leaderboard row format for holograms.
     * e.g. "§e#1 §7[§bDiamond§7] §fResync §8— §e1624 ELO"
     */
    public function renderLeaderboardRow(int $position, PlayerProfile $profile): string {
        $posColor = match(true) {
            $position === 1 => '§6',
            $position === 2 => '§f',
            $position === 3 => '§c',
            default         => '§7',
        };

        $badge = $profile->getDivision()->getDisplayName();
        $name  = $profile->getName();
        $elo   = $profile->getGlobalElo();

        return "{$posColor}#{$position} §7[{$badge}§7] §f{$name} §8— {$posColor}{$elo} ELO";
    }
}
