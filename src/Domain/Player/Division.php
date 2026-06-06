<?php

declare(strict_types=1);

namespace Domain\Player;

/**
 * Autorska definicja rang rankingowych oparta na PHP Enum.
 * Zintegrowana ze statystykami i scoreboardem NoMercy.
 */
enum Division: string {

    case IRON     = 'iron';
    case BRONZE   = 'bronze';
    case SILVER   = 'silver';
    case GOLD     = 'gold';
    case DIAMOND  = 'diamond';
    case ELITE    = 'elite';

    /**
     * Automatyczne przeliczanie punktów ELO na dywizję.
     */
    public static function fromElo(int $elo): self {
        return match(true) {
            $elo >= 2000 => self::ELITE,
            $elo >= 1600 => self::DIAMOND,
            $elo >= 1300 => self::GOLD,
            $elo >= 1100 => self::SILVER,
            $elo >= 900  => self::BRONZE,
            default      => self::IRON,
        };
    }

    /**
     * Zwraca minimalny próg punktowy danej dywizji.
     */
    public function getMinElo(): int {
        return match($this) {
            self::IRON    => 0,
            self::BRONZE  => 900,
            self::SILVER  => 1100,
            self::GOLD    => 1300,
            self::DIAMOND => 1600,
            self::ELITE   => 2000,
        };
    }

    /**
     * Pobiera kolejną dywizję awansu.
     */
    public function getNextDivision(): ?self {
        return match($this) {
            self::IRON    => self::BRONZE,
            self::BRONZE  => self::SILVER,
            self::SILVER  => self::GOLD,
            self::GOLD    => self::DIAMOND,
            self::DIAMOND => self::ELITE,
            self::ELITE   => null,
        };
    }

    /**
     * Oblicza, ile punktów ELO brakuje graczowi do awansu.
     */
    public function eloToNextDivision(int $currentElo): ?int {
        $next = $this->getNextDivision();
        if ($next === null) {
            return null;
        }
        return max(0, $next->getMinElo() - $currentElo);
    }

    /**
     * Kolorowa nazwa dywizji gotowa do wrzucenia na czat / scoreboard.
     */
    public function getDisplayName(): string {
        return match($this) {
            self::IRON    => '§7Iron',
            self::BRONZE  => '§6Bronze',
            self::SILVER  => '§fSilver',
            self::GOLD    => '§eGold',
            self::DIAMOND => '§bDiamond',
            self::ELITE   => '§cElite',
        };
    }

    /**
     * Sam kolor Minecraft powiązany z dywizją.
     */
    public function getColor(): string {
        return match($this) {
            self::IRON    => '§7',
            self::BRONZE  => '§6',
            self::SILVER  => '§f',
            self::GOLD    => '§e',
            self::DIAMOND => '§b',
            self::ELITE   => '§c',
        };
    }
}