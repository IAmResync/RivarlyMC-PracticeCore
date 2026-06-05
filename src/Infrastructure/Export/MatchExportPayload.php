<?php

declare(strict_types=1);

namespace Infrastructure\Export;

/**
 * TODO: DTO (Data Transfer Object) z kompletnym zestawem danych o jednym meczu.
 * Definiuje kontrakt JSON wysyłany do Next.js przez WebhookDispatcher po meczu.
 * Zawiera: gracze, tryb, ELO delta, accuracy, kills, czas trwania, timestamp.
 */
final class MatchExportPayload {

    public function __construct(
        public string $matchId,
        public string $gameMode,
        public string $winnerUuid,
        public string $loserUuid,
        public int $durationSecounds
    ) {}

    public function toArray(): array {
        return [
            'matchId' => $this->matchId,
            'mode' => $this->gameMode,
            'results' => [
                'winner_uuid' => $this->winnerUuid,
                'loser_uuid' => $this->loserUuid
            ],
            'duration' => $this->durationSecounds
        ];
    }
}
