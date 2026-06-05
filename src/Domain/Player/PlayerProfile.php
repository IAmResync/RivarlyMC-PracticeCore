<?php

declare(strict_types=1);

namespace Domain\Player;

/**
 * Domain entity representing a player profile.
 * Pure PHP — no PocketMine dependency.
 *
 * A player profile has:
 *   - UUID and XUID for identification
 *   - Username for display
 *   - Global ELO rating
 *   - Global statistics (kills, deaths)
 *   - Per-mode statistics (nodebuff, sumo, etc.)
 *   - Current kill streak
 */
final class PlayerProfile {

    private int $globalKills = 0;
    private int $globalDeaths = 0;
    private int $currentKillStreak = 0;

    /** @var array<string, PerModeStats> game mode => stats */
    private array $perModeStats = [];

    public function __construct(
        private readonly string $uuid,
        private readonly string $xuid,
        private readonly string $name,
        private int $globalElo = 1000,
    ) {
        $this->currentKillStreak = 0;
    }

    /**
     * Ustawia kills i deaths załadowane z bazy danych przy tworzeniu sesji.
     * Wywoływane tylko raz — zaraz po odczycie z PlayerRepository.
     */
    public function setLoadedStats(int $kills, int $deaths): void {
        $this->globalKills = max(0, $kills);
        $this->globalDeaths = max(0, $deaths);
    }

    // -----------------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------------

    public function getUuid(): string {
        return $this->uuid;
    }

    public function getXuid(): string {
        return $this->xuid;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getGlobalElo(): int {
        return $this->globalElo;
    }

    public function getGlobalKills(): int {
        return $this->globalKills;
    }

    public function getGlobalDeaths(): int {
        return $this->globalDeaths;
    }

    public function getCurrentKillStreak(): int {
        return $this->currentKillStreak;
    }

    public function getKDR(): float {
        return $this->globalDeaths > 0 ? round($this->globalKills / $this->globalDeaths, 2) : 0.0;
    }

    // -----------------------------------------------------------------------
    // ELO Management
    // -----------------------------------------------------------------------

    public function setGlobalElo(int $elo): void {
        $this->globalElo = max(0, $elo);
    }

    public function addElo(int $amount): void {
        $this->globalElo = max(0, $this->globalElo + $amount);
    }

    public function subtractElo(int $amount): void {
        $this->globalElo = max(0, $this->globalElo - $amount);
    }

    // -----------------------------------------------------------------------
    // Statistics Management
    // -----------------------------------------------------------------------

    public function addKill(): void {
        $this->globalKills++;
        $this->currentKillStreak++;
    }

    public function addDeath(): void {
        $this->globalDeaths++;
        $this->currentKillStreak = 0;
    }

    public function resetKillStreak(): void {
        $this->currentKillStreak = 0;
    }

    // -----------------------------------------------------------------------
    // Per-Mode Statistics
    // -----------------------------------------------------------------------

    public function getPerModeStats(string $gameMode): PerModeStats {
        if (!isset($this->perModeStats[$gameMode])) {
            $this->perModeStats[$gameMode] = new PerModeStats();
        }
        return $this->perModeStats[$gameMode];
    }

    // -----------------------------------------------------------------------
    // Serialization
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toPersistenceArray(): array {
        return [
            'uuid' => $this->uuid,
            'xuid' => $this->xuid,
            'name' => $this->name,
            'global_elo' => $this->globalElo,
            'global_kills' => $this->globalKills,
            'global_deaths' => $this->globalDeaths,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromPersistenceArray(array $row): self {
        $profile = new self(
            uuid: (string) ($row['uuid'] ?? ''),
            xuid: (string) ($row['xuid'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            globalElo: (int) ($row['global_elo'] ?? 1000),
        );
        $profile->setLoadedStats(
            kills: (int) ($row['global_kills'] ?? 0),
            deaths: (int) ($row['global_deaths'] ?? 0),
        );
        return $profile;
    }
}
