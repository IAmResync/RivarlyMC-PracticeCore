<?php

declare(strict_types=1);

namespace Domain\Player;

/**
 * Jeden wpis w historii zmian ELO gracza.
 * W pełni dostosowany pod gettery i automatyczny zapis bazy danych NoMercy.
 */
final class EloHistoryEntry {

    private int $before;
    private int $after;
    private int $delta;
    private string $opponentName;
    private bool $won;
    private int $timestamp;

    public function __construct(
        int $before,
        int $after,
        int $delta,
        string $opponentName,
        bool $won,
        int $timestamp
    ) {
        $this->before = $before;
        $this->after = $after;
        $this->delta = $delta;
        $this->opponentName = $opponentName;
        $this->won = $won;
        $this->timestamp = $timestamp;
    }

    // -----------------------------------------------------------------------
    // Bezpieczne gettery dla Twoich systemów
    // -----------------------------------------------------------------------

    public function getBefore(): int { return $this->before; }
    public function getAfter(): int { return $this->after; }
    public function getDelta(): int { return $this->delta; }
    public function getOpponentName(): string { return $this->opponentName; }
    public function isWon(): bool { return $this->won; }
    public function getTimestamp(): int { return $this->timestamp; }

    /**
     * Sprawdza, czy zmiana ELO była na plus.
     */
    public function isPositive(): bool {
        return $this->delta > 0;
    }

    /**
     * Zwraca sformatowaną zmianę, np. "+14" lub "-12". Przydatne do wiadomości na czacie po walce!
     */
    public function getFormattedDelta(): string {
        return ($this->delta >= 0 ? '+' : '') . $this->delta;
    }

    /**
     * Serializacja wpisu historii do tablicy JSON.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'before'    => $this->before,
            'after'     => $this->after,
            'delta'     => $this->delta,
            'opponent'  => $this->opponentName,
            'won'       => $this->won,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Odtworzenie wpisu historii z danych z bazy danych.
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        return new self(
            before: (int) ($data['before'] ?? 1000),
            after: (int) ($data['after'] ?? 1000),
            delta: (int) ($data['delta'] ?? 0),
            opponentName: (string) ($data['opponent'] ?? 'Unknown'),
            won: (bool) ($data['won'] ?? false),
            timestamp: (int) ($data['timestamp'] ?? time())
        );
    }
}