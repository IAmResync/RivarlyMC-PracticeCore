<?php

declare(strict_types=1);

namespace GameMode\Boxing;

/**
 * Tracks live Boxing state for one match (one pair of players).
 * Immutable after construction except hit counts — no PMMP dependency.
 *
 * Boxing rules:
 * - HP resets to 20 after every successful hit (no one can die)
 * - First player to reach maxHits wins
 * - If time runs out, player with more hits wins (tie = draw)
 *
 * One BoxingSession per active match, owned by BoxingMode.
 * Destroyed when the match ends.
 */
final class BoxingSession {

    /** @var array<string, int> uuid => hit count */
    private array $hits = [];

    /** @var array<string, string> uuid => player name (for display) */
    private array $names = [];

    private int $maxHits;

    public function __construct(
        string $uuidA,
        string $nameA,
        string $uuidB,
        string $nameB,
        int    $maxHits = 100,
    ) {
        $this->hits[$uuidA] = 0;
        $this->hits[$uuidB] = 0;
        $this->names[$uuidA] = $nameA;
        $this->names[$uuidB] = $nameB;
        $this->maxHits = $maxHits;
    }

    // -----------------------------------------------------------------------
    // Recording hits
    // -----------------------------------------------------------------------

    /**
     * Records a hit from attacker to victim.
     * Returns true if this hit ends the match (attacker reached maxHits).
     */
    public function recordHit(string $attackerUuid): bool {
        if (!isset($this->hits[$attackerUuid])) {
            return false;
        }

        $this->hits[$attackerUuid]++;
        return $this->hits[$attackerUuid] >= $this->maxHits;
    }

    // -----------------------------------------------------------------------
    // State queries
    // -----------------------------------------------------------------------

    public function getHits(string $uuid): int {
        return $this->hits[$uuid] ?? 0;
    }

    /**
     * Returns uuid of the player currently leading by hit count.
     * Returns null if tied.
     */
    public function getLeader(): ?string {
        $uuids = array_keys($this->hits);
        if (count($uuids) < 2) return null;

        [$a, $b] = $uuids;
        if ($this->hits[$a] === $this->hits[$b]) return null;

        return $this->hits[$a] > $this->hits[$b] ? $a : $b;
    }

    /**
     * Returns uuid of the winner if someone reached maxHits, otherwise null.
     */
    public function getWinnerUuid(): ?string {
        foreach ($this->hits as $uuid => $count) {
            if ($count >= $this->maxHits) {
                return $uuid;
            }
        }
        return null;
    }

    /**
     * Returns winner by hit count when time runs out.
     * Returns null if it's a draw.
     */
    public function getWinnerByTimeOut(): ?string {
        return $this->getLeader();
    }

    public function isTied(): bool {
        $values = array_values($this->hits);
        return count($values) === 2 && $values[0] === $values[1];
    }

    /**
     * Formatted scoreline for action bar / scoreboard.
     * e.g. "Resync §c47 §7| §aNoMercy §c31"
     */
    public function getScoreLine(): string {
        $parts = [];
        foreach ($this->hits as $uuid => $count) {
            $name = $this->names[$uuid] ?? $uuid;
            $parts[] = "§f{$name} §c{$count}";
        }
        return implode(' §7| ', $parts);
    }

    /** @return array<string, int> uuid => hits */
    public function getAllHits(): array {
        return $this->hits;
    }

    /** @return array<string, string> uuid => name */
    public function getNames(): array {
        return $this->names;
    }
}