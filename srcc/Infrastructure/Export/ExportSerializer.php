<?php

declare(strict_types=1);

namespace Infrastructure\Export;

use Domain\Match\MatchResult;
use Domain\Player\PlayerProfile;

/**
 * TODO: Konwertuje encje domenowe (PlayerProfile, MatchResult) na gotowe DTO do eksportu.
 * Zapewnia spójny format JSON nawet gdy wewnętrzna struktura danych się zmieni.
 * Jedyne miejsce w projekcie które "wie" jak Domain mapuje się na API kontrakt.
 */
final class ExportSerializer {

    /**
     * Mapuje obiekt profilu gracza z gry na bezpieczny format eksportowy.
     */
    public function serializePlayer(PlayerProfile $profile): PlayerExportPayload {
        return new PlayerExportPayload(
            username: $profile->getName(),
            elo: $profile->getGlobalElo(),
            kills: $profile->getGlobalKills(),
            deaths: $profile->getGlobalDeaths(),
            kdr: $profile->getKdr()
        );
    }

    /**
     * Mapuje czysty wynik meczu z silnika gry na format dla Next.js / Webhooków
     */
    public function serializeMatch(MatchResult $match): MatchExportPayload {

        return new MatchExportPayload(
            matchId: $match->getMatchId(),
            gameMode: $match->getGameMode(),
            winnerUuid: $match->getWinnerUuid(),
            loserUuid: $match->getLoserUuid(),
            durationSecounds: $match->getDurationSeconds()
        );
    }
}
