<?php

declare(strict_types=1);

namespace Duel;

/**
 * TODO: Immutable value object reprezentujący zaproszenie do prywatnego 1v1.
 * Przechowuje UUID obu graczy, wybrany tryb gry oraz timestamp (timeout 30 sekund).
 * Po 30 sekundach bez odpowiedzi DuelManager automatycznie usuwa request.
 */
final class DuelRequest {

    public const TIMEOUT_SECONDS = 30;

    public readonly int $expiresAt;

    public function __construct(
        public readonly string $senderUuid,
        public readonly string $senderName,
        public readonly string $receiverUuid,
        public readonly string $receiverName,
        public readonly string $gameMode,
        public readonly int    $createdAt = 0
    ) {
        // Jeśli nie podano czasu stworzenia, bierzemy aktualny timestamp systemu
        $this->expiresAt = ($createdAt === 0 ? time() : $createdAt) + self::TIMEOUT_SECONDS;
    }

    /**
     * Sprawdza, czy zaproszenie już wygasło (minęło 30 sekund).
     */
    public function isExpired(): bool {
        return time() >= $this->expiresAt;
    }

    /**
     * Zwraca ile sekund pozostało do wygaśnięcia zaproszenia.
     */
    public function secondsRemaining(): int {
        return max(0, $this->expiresAt - time());
    }

    /**
     * Generuje unikalny klucz identyfikujący parę nadawca -> odbiorca.
     * Zapobiega to spamowaniu wieloma zaproszeniami do tej samej osoby.
     */
    public function getKey(): string {
        return self::makeKey($this->senderUuid, $this->receiverUuid);
    }

    /**
     * Statyczna metoda pomocnicza do szybkiego wyszukiwania klucza w tablicach managera.
     */
    public static function makeKey(string $senderUuid, string $receiverUuid): string {
        return "{$senderUuid}:{$receiverUuid}";
    }
}