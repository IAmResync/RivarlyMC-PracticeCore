<?php

declare(strict_types=1);

namespace Rematch;

/**
 * TODO: Value object reprezentujący prośbę o rewanż po zakończonym meczu.
 * Zawiera UUID obu graczy, tryb gry i timestamp (timeout 15 sekund na odpowiedź).
 * Jeśli obaj gracze zaakceptują, mecz startuje natychmiast bez kolejki.
 */
final class RematchRequest {

    // Poprawione z 30 na 15 sekund zgodnie z wymaganiem
    public const TIMEOUT_SECONDS = 15;

    public readonly int $expiresAt;

    public function __construct(
        public readonly string $senderUuid,
        public readonly string $senderName,
        public readonly string $receiverUuid,
        public readonly string $receiverName,
        public readonly string $gameMode,
        public readonly int    $createdAt = 0
    ) {
        $this->expiresAt = ($createdAt === 0 ? time() : $createdAt) + self::TIMEOUT_SECONDS;
    }

    /**
     * Sprawdza, czy prośba o rewanż już wygasła (minęło 15 sekund).
     */
    public function isExpired(): bool {
        return time() >= $this->expiresAt;
    }

    /**
     * Zwraca pozostały czas na akceptację rewanżu.
     */
    public function secondsRemaining(): int {
        return max(0, $this->expiresAt - time());
    }

    /**
     * Generuje unikalny klucz identyfikujący parę rewanżową nadawca -> odbiorca.
     */
    public function getKey(): string {
        return self::makeKey($this->senderUuid, $this->receiverUuid);
    }

    /**
     * Statyczna metoda pomocnicza do pobierania klucza.
     */
    public static function makeKey(string $senderUuid, string $receiverUuid): string {
        return "rematch:{$senderUuid}:{$receiverUuid}";
    }
}