<?php

declare(strict_types=1);

namespace Application\Matchmaking;

use Domain\Player\PlayerProfile;
use Domain\Player\PlayerState;

/**
 * Zarządza wyłącznie pamięcią podręczną (In-memory) graczy oczekujących w kolejce.
 * Odseparowany od logiki parowania zgodnie z architekturą NoMercy.
 */
final class QueueManager {

    /** @var array<string, array<string, QueueEntry>> gameMode => uuid => entry */
    private array $queues = [];

    /**
     * Dodaje gracza do kolejki danego trybu.
     */
    public function enqueue(PlayerProfile $profile, string $gameMode): bool {
        $uuid = $profile->getUuid();

        if ($this->isInQueue($uuid)) {
            return false;
        }

        // Blokada, jeśli gracz już jest na arenie
        if ($profile->getState() === PlayerState::IN_MATCH) {
            return false;
        }

        if (!isset($this->queues[$gameMode])) {
            $this->queues[$gameMode] = [];
        }

        $this->queues[$gameMode][$uuid] = new QueueEntry(
            uuid:       $uuid,
            name:       $profile->getName(),
            elo:        $profile->getGlobalElo(),
            gameMode:   $gameMode,
            enqueuedAt: time(),
        );

        return true;
    }

    /**
     * Usuwa gracza z kolejki danego trybu.
     */
    public function dequeue(string $uuid, string $gameMode): bool {
        if (!isset($this->queues[$gameMode][$uuid])) {
            return false;
        }

        unset($this->queues[$gameMode][$uuid]);
        return true;
    }

    /**
     * Usuwa gracza z absolutnie wszystkich kolejek (np. przy wyjściu z serwera).
     */
    public function dequeueAll(string $uuid): void {
        foreach ($this->queues as $gameMode => &$entries) {
            unset($entries[$uuid]);
        }
    }

    public function isInQueue(string $uuid): bool {
        foreach ($this->queues as $entries) {
            if (isset($entries[$uuid])) {
                return true;
            }
        }
        return false;
    }

    public function getQueueEntry(string $uuid): ?QueueEntry {
        foreach ($this->queues as $entries) {
            if (isset($entries[$uuid])) {
                return $entries[$uuid];
            }
        }
        return null;
    }

    /**
     * Eksponuje listę oczekujących dla Matchmakera.
     * @return array<string, QueueEntry> uuid => entry
     */
    public function getWaiting(string $gameMode): array {
        return $this->queues[$gameMode] ?? [];
    }

    public function getQueueSize(string $gameMode): int {
        return count($this->queues[$gameMode] ?? []);
    }

    /** * @return array<string, int> gameMode => liczba graczy
     */
    public function getAllQueueSizes(): array {
        $sizes = [];
        foreach ($this->queues as $gameMode => $entries) {
            $sizes[$gameMode] = count($entries);
        }
        return $sizes;
    }

    /**
     * Cyklicznie rozszerza akceptowalne widełki ELO (wywoływane przez Task NoMercy).
     */
    public function expandEloRanges(int $expandBy): void {
        foreach ($this->queues as &$entries) {
            foreach ($entries as $entry) {
                $entry->expandEloRange($expandBy);
            }
        }
    }
}

// ---------------------------------------------------------------------------
// Obiekt wartości (Value Object) reprezentujący wpis w kolejce
// ---------------------------------------------------------------------------

final class QueueEntry {

    private int $eloRange;

    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly int    $elo,
        public readonly string $gameMode,
        public readonly int    $enqueuedAt,
        int                    $initialRange = 100,
    ) {
        $this->eloRange = $initialRange;
    }

    public function getEloRange(): int {
        return $this->eloRange;
    }

    public function expandEloRange(int $amount): void {
        $this->eloRange += $amount;
    }

    public function getWaitSeconds(): int {
        return time() - $this->enqueuedAt;
    }

    /**
     * Weryfikuje dopasowanie ELO – warunek działa w dwie strony (symetryczność)
     */
    public function isEloCompatible(QueueEntry $other): bool {
        $diff = abs($this->elo - $other->elo);
        return $diff <= $this->eloRange && $diff <= $other->eloRange;
    }
}