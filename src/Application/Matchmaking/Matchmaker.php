<?php

declare(strict_types=1);

namespace Application\Matchmaking;

/**
 * Zoptymalizowany i wydajny algorytm dobierania par NoMercy.
 * Przeszukuje kolejki w czasie rzeczywistym bez zbędnego obciążania procesora.
 */
final class Matchmaker {

    public function __construct(
        private readonly QueueManager $queue,
    ) {}

    /**
     * Szuka wszystkich możliwych par we wszystkich trybach gry za jednym zamachem.
     * Sortuje dane tylko raz na tick, eliminując ryzyko lagów procesora.
     *
     * @return array<string, list<MatchPair>> Mapa: gameMode => lista dopasowanych par
     */
    public function findAllMatches(): array {
        $results = [];

        foreach (array_keys($this->queue->getAllQueueSizes()) as $gameMode) {
            $waiting = $this->queue->getWaiting($gameMode);

            if (count($waiting) < 2) {
                continue;
            }

            // 1. Sortujemy graczy po ELO tylko RAZ dla danego trybu
            uasort($waiting, fn(QueueEntry $a, QueueEntry $b) => $a->elo <=> $b->elo);

            /** @var list<QueueEntry> $entries */
            $entries = array_values($waiting);
            $foundPairs = [];
            $skipUuids = []; // Zapamiętuje graczy, którzy już znaleźli parę w tym ticku

            // 2. Szukamy dopasowań w bezpiecznej, pojedynczej pętli
            for ($i = 0; $i < count($entries); $i++) {
                $playerA = $entries[$i];
                if (isset($skipUuids[$playerA->uuid])) {
                    continue;
                }

                for ($j = $i + 1; $j < count($entries); $j++) {
                    $playerB = $entries[$j];
                    if (isset($skipUuids[$playerB->uuid])) {
                        continue;
                    }

                    // Sprawdzamy symetryczną kompatybilność widełek ELO
                    if ($playerA->isEloCompatible($playerB)) {
                        // Tworzymy nową parę
                        $pair = new MatchPair(
                            playerAUuid: $playerA->uuid,
                            playerAName: $playerA->name,
                            playerAElo:  $playerA->elo,
                            playerBUuid: $playerB->uuid,
                            playerBName: $playerB->name,
                            playerBElo:  $playerB->elo,
                            gameMode:    $gameMode,
                        );

                        $foundPairs[] = $pair;

                        // Oznaczamy ich jako zajętych, żeby nie sparować ich z nikim innym
                        $skipUuids[$playerA->uuid] = true;
                        $skipUuids[$playerB->uuid] = true;

                        // Wyciągamy ich z oficjalnej kolejki głównej
                        $this->queue->dequeue($playerA->uuid, $gameMode);
                        $this->queue->dequeue($playerB->uuid, $gameMode);
                        break;
                    }
                }
            }

            if (!empty($foundPairs)) {
                $results[$gameMode] = $foundPairs;
            }
        }

        return $results;
    }
}

// ---------------------------------------------------------------------------
// Niezmienny (immutable) obiekt dopasowanej pary walki
// ---------------------------------------------------------------------------

final class MatchPair {

    public function __construct(
        public readonly string $playerAUuid,
        public readonly string $playerAName,
        public readonly int    $playerAElo,
        public readonly string $playerBUuid,
        public readonly string $playerBName,
        public readonly int    $playerBElo,
        public readonly string $gameMode,
    ) {}

    /**
     * Zwraca bezwzględną różnicę rankingu ELO pomiędzy zawodnikami.
     */
    public function getEloDiff(): int {
        return abs($this->playerAElo - $this->playerBElo);
    }

    /**
     * Wyłania faworyta pojedynku (gracza z wyższym rankingiem wyjściowym).
     */
    public function getFavoredUuid(): string {
        return $this->playerAElo >= $this->playerBElo ? $this->playerAUuid : $this->playerBUuid;
    }

    /**
     * Pakuje dane konkretnego zawodnika z pary do tablicy.
     * @return array{uuid: string, name: string, elo: int}
     */
    public function toPlayerArray(string $uuid): array {
        if ($uuid === $this->playerAUuid) {
            return ['uuid' => $this->playerAUuid, 'name' => $this->playerAName, 'elo' => $this->playerAElo];
        }
        return ['uuid' => $this->playerBUuid, 'name' => $this->playerBName, 'elo' => $this->playerBElo];
    }
}