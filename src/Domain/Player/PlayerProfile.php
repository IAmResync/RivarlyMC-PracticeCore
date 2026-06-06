<?php

declare(strict_types=1);

namespace Domain\Player;

/**
 * Encja profilu gracza – pełny stan gracza trzymany w RAM podczas sesji.
 */
final class PlayerProfile {

    private string $uuid;
    private string $xuid;
    private string $name;
    private int $firstLoginAt;
    private int $lastLoginAt;
    private int $totalPlaytimeSeconds;

    private PlayerState $state;
    private ?string $currentMatchId = null;
    private ?string $currentQueueMode = null;
    private int $sessionStartAt;

    private int $globalElo;
    private Division $division;
    private int $globalWins;
    private int $globalLosses;
    private int $globalKills;
    private int $globalDeaths;

    /** @var array<string, PerModeStats> */
    private array $modeStats = [];

    private int $currentWinStreak;
    private int $bestWinStreak;
    private int $currentKillStreak;
    private int $bestKillStreak;
    private int $totalMatchesPlayed;
    private int $longestMatchSeconds;

    private const ELO_HISTORY_LIMIT = 30;
    /** @var list<EloHistoryEntry> */
    private array $eloHistory = [];

    /** @var list<string> */
    private array $unlockedCapes = [];
    /** @var list<string> */
    private array $unlockedKillEffects = [];
    private ?string $activeCape = null;
    private ?string $activeKillEffect = null;

    /** @var list<string> */
    private array $friendUuids = [];
    /** @var list<string> */
    private array $pendingFriendRequests = [];

    private bool $acceptingDuels = true;
    private bool $spectatorEnabled = true;

    private int $tournamentWins = 0;
    private int $tournamentParticipations = 0;

    public function __construct(
        string $uuid,
        string $xuid,
        string $name,
        int $globalElo = 1000,
        int $firstLoginAt = 0
    ) {
        $this->uuid = $uuid;
        $this->xuid = $xuid;
        $this->name = $name;
        $this->globalElo = $globalElo;
        $this->division = Division::fromElo($globalElo);
        $this->firstLoginAt = $firstLoginAt === 0 ? time() : $firstLoginAt;
        $this->lastLoginAt = time();
        $this->sessionStartAt = time();
        $this->totalPlaytimeSeconds = 0;
        $this->state = PlayerState::LOBBY;
        $this->globalWins = 0;
        $this->globalLosses = 0;
        $this->globalKills = 0;
        $this->globalDeaths = 0;
        $this->currentWinStreak = 0;
        $this->bestWinStreak = 0;
        $this->currentKillStreak = 0;
        $this->bestKillStreak = 0;
        $this->totalMatchesPlayed = 0;
        $this->longestMatchSeconds = 0;
    }

    // -----------------------------------------------------------------------
    // ELO, Kills, Deaths
    // -----------------------------------------------------------------------

    public function getGlobalElo(): int {
        return $this->globalElo;
    }

    public function setGlobalElo(int $elo): void {
        $this->globalElo = max(0, $elo);
        $this->division = Division::fromElo($this->globalElo);
    }

    public function getGlobalKills(): int {
        return $this->globalKills;
    }

    public function addGlobalKill(): void {
        $this->globalKills++;
        $this->currentKillStreak++;
        if ($this->currentKillStreak > $this->bestKillStreak) {
            $this->bestKillStreak = $this->currentKillStreak;
        }
    }

    public function getGlobalDeaths(): int {
        return $this->globalDeaths;
    }

    public function addGlobalDeath(): void {
        $this->globalDeaths++;
        $this->currentKillStreak = 0;
    }

    /**
     * Ustawia kills i deaths załadowane z bazy danych przy tworzeniu sesji.
     */
    public function setLoadedStats(int $kills, int $deaths): void {
        $this->globalKills = max(0, $kills);
        $this->globalDeaths = max(0, $deaths);
    }

    public function getDivision(): string {
        return $this->division->getDisplayName();
    }

    public function getDivisionEnum(): Division {
        return $this->division;
    }

    public function setDivision(Division $division): void {
        $this->division = $division;
    }

    // -----------------------------------------------------------------------
    // ELO delta, wins/losses, streaks
    // -----------------------------------------------------------------------

    /**
     * Zmienia ELO o daną wartość i automatycznie zapisuje zdarzenie w historii.
     * $gameMode jest opcjonalny dla kompatybilności wstecznej.
     */
    public function applyEloDelta(int $delta, string $opponentName, bool $won, string $gameMode = ''): void {
        $before = $this->globalElo;
        $this->globalElo = max(0, $this->globalElo + $delta);
        $this->division = Division::fromElo($this->globalElo);

        $entry = new EloHistoryEntry(
            before:       $before,
            after:        $this->globalElo,
            delta:        $delta,
            opponentName: $opponentName,
            won:          $won,
            timestamp:    time()
        );

        array_unshift($this->eloHistory, $entry);

        if (count($this->eloHistory) > self::ELO_HISTORY_LIMIT) {
            array_pop($this->eloHistory);
        }
    }

    /** @return list<EloHistoryEntry> */
    public function getEloHistory(): array {
        return $this->eloHistory;
    }

    public function recordWin(string $gameMode, int $matchDurationSeconds): void {
        $this->globalWins++;
        $this->totalMatchesPlayed++;
        $this->currentWinStreak++;

        if ($this->currentWinStreak > $this->bestWinStreak) {
            $this->bestWinStreak = $this->currentWinStreak;
        }
        if ($matchDurationSeconds > $this->longestMatchSeconds) {
            $this->longestMatchSeconds = $matchDurationSeconds;
        }

        $this->getOrCreateModeStats($gameMode)->recordWin();
    }

    public function recordLoss(string $gameMode, int $matchDurationSeconds): void {
        $this->globalLosses++;
        $this->totalMatchesPlayed++;
        $this->currentWinStreak = 0;

        if ($matchDurationSeconds > $this->longestMatchSeconds) {
            $this->longestMatchSeconds = $matchDurationSeconds;
        }

        $this->getOrCreateModeStats($gameMode)->recordLoss();
    }

    public function recordKill(string $gameMode): void {
        $this->addGlobalKill();
        $this->getOrCreateModeStats($gameMode)->recordKill();
    }

    public function recordDeath(string $gameMode): void {
        $this->addGlobalDeath();
        $this->getOrCreateModeStats($gameMode)->recordDeath();
    }

    public function resetMatchKillStreak(): void {
        $this->currentKillStreak = 0;
    }

    // -----------------------------------------------------------------------
    // Per-mode stats
    // -----------------------------------------------------------------------

    public function getModeStats(string $gameMode): ?PerModeStats {
        return $this->modeStats[$gameMode] ?? null;
    }

    public function getOrCreateModeStats(string $gameMode): PerModeStats {
        if (!isset($this->modeStats[$gameMode])) {
            $this->modeStats[$gameMode] = new PerModeStats($gameMode);
        }
        return $this->modeStats[$gameMode];
    }

    /** @return array<string, PerModeStats> */
    public function getAllModeStats(): array {
        return $this->modeStats;
    }

    // -----------------------------------------------------------------------
    // Computed
    // -----------------------------------------------------------------------

    public function getWinRate(): float {
        if ($this->totalMatchesPlayed === 0) return 0.0;
        return round(($this->globalWins / $this->totalMatchesPlayed) * 100, 2);
    }

    public function getKdr(): float {
        if ($this->globalDeaths === 0) return (float) $this->globalKills;
        return round($this->globalKills / $this->globalDeaths, 2);
    }

    // -----------------------------------------------------------------------
    // State management
    // -----------------------------------------------------------------------

    public function getState(): PlayerState {
        return $this->state;
    }

    public function setState(PlayerState $state): void {
        $this->state = $state;
    }

    public function isInMatch(): bool { return $this->state === PlayerState::IN_MATCH; }
    public function isInQueue(): bool { return $this->state === PlayerState::IN_QUEUE; }
    public function isInLobby(): bool { return $this->state === PlayerState::LOBBY; }
    public function isInTournament(): bool { return $this->state === PlayerState::IN_TOURNAMENT; }

    public function setCurrentMatchId(?string $matchId): void { $this->currentMatchId = $matchId; }
    public function getCurrentMatchId(): ?string { return $this->currentMatchId; }

    public function setCurrentQueueMode(?string $mode): void { $this->currentQueueMode = $mode; }
    public function getCurrentQueueMode(): ?string { return $this->currentQueueMode; }

    public function tickPlaytime(): void { $this->totalPlaytimeSeconds++; }
    public function getTotalPlaytimeSeconds(): int { return $this->totalPlaytimeSeconds; }
    public function getCurrentSessionSeconds(): int { return time() - $this->sessionStartAt; }

    // -----------------------------------------------------------------------
    // Cosmetics, Social, Tournaments
    // -----------------------------------------------------------------------

    public function unlockCape(string $capeId): void {
        if (!in_array($capeId, $this->unlockedCapes, true)) $this->unlockedCapes[] = $capeId;
    }
    public function setActiveCape(?string $capeId): bool {
        if ($capeId !== null && !in_array($capeId, $this->unlockedCapes, true)) return false;
        $this->activeCape = $capeId;
        return true;
    }
    public function getActiveCape(): ?string { return $this->activeCape; }

    public function unlockKillEffect(string $effectId): void {
        if (!in_array($effectId, $this->unlockedKillEffects, true)) $this->unlockedKillEffects[] = $effectId;
    }
    public function setActiveKillEffect(?string $effectId): bool {
        if ($effectId !== null && !in_array($effectId, $this->unlockedKillEffects, true)) return false;
        $this->activeKillEffect = $effectId;
        return true;
    }
    public function getActiveKillEffect(): ?string { return $this->activeKillEffect; }

    public function addFriend(string $uuid): void {
        if (!in_array($uuid, $this->friendUuids, true)) $this->friendUuids[] = $uuid;
        $this->pendingFriendRequests = array_values(array_filter($this->pendingFriendRequests, fn($u) => $u !== $uuid));
    }
    public function removeFriend(string $uuid): void {
        $this->friendUuids = array_values(array_filter($this->friendUuids, fn($u) => $u !== $uuid));
    }
    public function hasFriend(string $uuid): bool { return in_array($uuid, $this->friendUuids, true); }
    public function sendFriendRequest(string $uuid): void {
        if (!in_array($uuid, $this->pendingFriendRequests, true)) $this->pendingFriendRequests[] = $uuid;
    }
    public function hasPendingRequestFrom(string $uuid): bool {
        return in_array($uuid, $this->pendingFriendRequests, true);
    }
    /** @return list<string> */
    public function getFriendUuids(): array { return $this->friendUuids; }

    public function isAcceptingDuels(): bool { return $this->acceptingDuels; }
    public function setAcceptingDuels(bool $value): void { $this->acceptingDuels = $value; }
    public function isSpectatorEnabled(): bool { return $this->spectatorEnabled; }
    public function setSpectatorEnabled(bool $value): void { $this->spectatorEnabled = $value; }

    public function recordTournamentParticipation(): void { $this->tournamentParticipations++; }
    public function recordTournamentWin(): void { $this->tournamentWins++; }
    public function getTournamentWins(): int { return $this->tournamentWins; }
    public function getTournamentParticipations(): int { return $this->tournamentParticipations; }

    // -----------------------------------------------------------------------
    // Basic getters
    // -----------------------------------------------------------------------

    public function getUuid(): string { return $this->uuid; }
    public function getXuid(): string { return $this->xuid; }
    public function getName(): string { return $this->name; }
    public function getFirstLoginAt(): int { return $this->firstLoginAt; }
    public function getLastLoginAt(): int { return $this->lastLoginAt; }
    public function getGlobalWins(): int { return $this->globalWins; }
    public function getGlobalLosses(): int { return $this->globalLosses; }
    public function getCurrentWinStreak(): int { return $this->currentWinStreak; }
    public function getBestWinStreak(): int { return $this->bestWinStreak; }
    public function getCurrentKillStreak(): int { return $this->currentKillStreak; }
    public function getBestKillStreak(): int { return $this->bestKillStreak; }
    public function getTotalMatchesPlayed(): int { return $this->totalMatchesPlayed; }
    public function getLongestMatchSeconds(): int { return $this->longestMatchSeconds; }
}
