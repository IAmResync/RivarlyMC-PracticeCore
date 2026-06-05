<?php

declare(strict_types=1);

namespace Party;

use Application\Match\MatchManager;
use Domain\Player\PlayerProfile;
use GameMode\GameModeRegistry;

/**
 * TODO: Łączy system party z systemem meczów dla walk grupowych (2v2, 3v3).
 * Waliduje czy oba party mają tę samą liczbę graczy przed startem meczu.
 * Przekazuje całe party jako jednostkę do MatchManager zamiast pojedynczych graczy.
 */
final class PartyDuelBridge {

    public function __construct(
        private readonly MatchManager     $matchManager,
        private readonly GameModeRegistry $gameModeRegistry
    ) {}

    /**
     * Rozpoczyna walkę drużynową (Party vs Party) jako jeden wspólny mecz (np. 2v2, 3v3).
     *
     * @param array<string, PlayerProfile> $partyAMembers Uczestnicy pierwszej drużyny
     * @param array<string, PlayerProfile> $partyBMembers Uczestnicy drugiej drużyny
     */
    public function startPartyDuel(
        array  $partyAMembers,
        array  $partyBMembers,
        string $gameMode
    ): PartyDuelResult {
        // 1. Walidacja istnienia trybu gry przy użyciu poprawnej metody exists() z GameModeRegistry
        if (!$this->gameModeRegistry->exists($gameMode)) {
            return PartyDuelResult::fail(PartyDuelFailReason::UNKNOWN_GAME_MODE);
        }

        // 2. Walidacja liczby graczy (wymóg z TODO: ta sama liczba graczy, np. 2v2, 3v3)
        if (count($partyAMembers) !== count($partyBMembers)) {
            return PartyDuelResult::fail(PartyDuelFailReason::PARTY_SIZE_MISMATCH);
        }

        // 3. Walidacja dostępności wszystkich członków obu drużyn (czy są w lobby)
        foreach ($partyAMembers as $player) {
            if (!$player->isInLobby()) {
                return PartyDuelResult::fail(PartyDuelFailReason::PLAYER_NOT_AVAILABLE);
            }
        }

        foreach ($partyBMembers as $player) {
            if (!$player->isInLobby()) {
                return PartyDuelResult::fail(PartyDuelFailReason::PLAYER_NOT_AVAILABLE);
            }
        }

        // Pobieramy liderów/reprezentantów jako główne jednostki do MatchPair
        $leaderA = reset($partyAMembers);
        $leaderB = reset($partyBMembers);

        if (!$leaderA instanceof PlayerProfile || !$leaderB instanceof PlayerProfile) {
            return PartyDuelResult::fail(PartyDuelFailReason::PLAYER_NOT_AVAILABLE);
        }

        // Pobieramy ELO liderów grup
        $eloA = method_exists($leaderA, 'getElo') ? $leaderA->getElo($gameMode) : 1000;
        $eloB = method_exists($leaderB, 'getElo') ? $leaderB->getElo($gameMode) : 1000;

        // 4. Przekazanie pary i reprezentantów do standardowej metody createMatch
        try {
            $pair = new \Rivarly\Application\Matchmaking\MatchPair(
                playerAUuid: $leaderA->getUuid(),
                playerAName: $leaderA->getName(),
                playerAElo: (int) $eloA,
                playerBUuid: $leaderB->getUuid(),
                playerBName: $leaderB->getName(),
                playerBElo: (int) $eloB,
                gameMode: $gameMode
            );

            // Wywołujemy istniejącą w MatchManager metodę createMatch
            $match = $this->matchManager->createMatch($pair, $leaderA, $leaderB);

            return PartyDuelResult::ok($match->getMatchId());
        } catch (\Throwable $e) {
            return PartyDuelResult::fail(PartyDuelFailReason::MATCH_CREATION_FAILED);
        }
    }
}

// ---------------------------------------------------------------------------
// Struktury wynikowe DTO
// ---------------------------------------------------------------------------

final class PartyDuelResult {

    private function __construct(
        public readonly bool                 $success,
        public readonly ?PartyDuelFailReason $reason = null,
        public readonly ?string              $matchId = null
    ) {}

    public static function ok(string $matchId): self {
        return new self(true, null, $matchId);
    }

    public static function fail(PartyDuelFailReason $reason): self {
        return new self(false, $reason, null);
    }
}

enum PartyDuelFailReason {
    case UNKNOWN_GAME_MODE;
    case PARTY_SIZE_MISMATCH;
    case PLAYER_NOT_AVAILABLE;
    case MATCH_CREATION_FAILED;
}