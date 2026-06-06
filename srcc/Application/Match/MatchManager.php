<?php

declare(strict_types=1);

namespace Application\Match;

use pocketmine\Server;
use Application\Matchmaking\MatchPair;
use Application\Player\StatsCollector;;
use Domain\Match\MatchResult;
use Domain\Match\MatchState;
use Domain\Player\PlayerProfile;
use Domain\Ranking\EloCalculator;
use Infrastructure\Database\MatchRepository;
use Infrastructure\Http\WebhookDispatcher;

/**
 * Centralny zarządca meczów NoMercy.
 * Aby uniknąć konfliktów z zarezerwowanym słowem kluczowym 'match' w PHP 8.0+,
 * bezpośrednie odwołania do encji meczu stosują w pełni kwalifikowaną ścieżkę.
 */
final class MatchManager {

    /** @var array<string, \Domain\Match\GameMatch> matchId => GameMatch */
    private array $activeMatches = [];

    /** @var array<string, string> playerUuid => matchId */
    private array $playerMatchIndex = [];

    public function __construct(
        private readonly StatsCollector   $statsCollector,
        private readonly MatchRepository   $matchRepository,
        private readonly WebhookDispatcher $webhookDispatcher,
    ) {}

    // -----------------------------------------------------------------------
    // Tworzenie meczu
    // -----------------------------------------------------------------------

    public function createMatch(
        MatchPair     $pair,
        PlayerProfile $profileA,
        PlayerProfile $profileB,
        ?string       $arenaId = null,
    ): ?\Domain\Match\GameMatch {
        $server = Server::getInstance();
        $playerA = $server->getPlayerExact($profileA->getName());
        $playerB = $server->getPlayerExact($profileB->getName());

        if ($playerA === null || $playerB === null) {
            return null;
        }

        $matchId = $this->generateMatchId();

        $match = new \Domain\Match\GameMatch(
            matchId:  $matchId,
            gameMode: $pair->gameMode,
            players:  [
                $pair->playerAUuid => $pair->playerAName,
                $pair->playerBUuid => $pair->playerBName,
            ],
            arenaId: $arenaId,
        );

        $this->activeMatches[$matchId] = $match;
        $this->playerMatchIndex[$pair->playerAUuid] = $matchId;
        $this->playerMatchIndex[$pair->playerBUuid] = $matchId;

        $profileA->resetMatchKillStreak();
        $profileB->resetMatchKillStreak();

        $this->statsCollector->startSession($playerA);
        $this->statsCollector->startSession($playerB);

        return $match;
    }

    // -----------------------------------------------------------------------
    // Kończenie meczu
    // -----------------------------------------------------------------------

    public function endMatch(
        string        $matchId,
        string        $winnerUuid,
        PlayerProfile $winnerProfile,
        PlayerProfile $loserProfile,
    ): ?MatchResult {
        $match = $this->activeMatches[$matchId] ?? null;

        // Używamy bezpiecznego stanu ENDING, który widnieje w Twoim enumie
        if ($match === null || $match->getState() === MatchState::ENDING) {
            return null;
        }

        $match->end($winnerUuid);

        $server = Server::getInstance();
        $playerW = $server->getPlayerExact($winnerProfile->getName());
        $playerL = $server->getPlayerExact($loserProfile->getName());

        $snapshots = [];
        if ($playerW !== null) {
            $winnerSnapshot = $this->statsCollector->endSession($playerW);
            if ($winnerSnapshot !== null) $snapshots[$winnerUuid] = $winnerSnapshot;
        }
        if ($playerL !== null) {
            $loserSnapshot = $this->statsCollector->endSession($playerL);
            if ($loserSnapshot !== null) $snapshots[$loserProfile->getUuid()] = $loserSnapshot;
        }

        // Przekazujemy całe profile, zgodnie z oczekiwaniami metody calculate()
        $eloResult = EloCalculator::calculate(
            winner:           $winnerProfile,
            loser:            $loserProfile,
            matchDurationSec: $match->getDuration()
        );

        // Wyciągamy dane z tablicy asocjacyjnej zwrotnej
        $winnerDelta = (int) ($eloResult['winnerGain'] ?? 0);
        $loserDelta  = (int) ($eloResult['loserLoss'] ?? 0);
        $isDominant  = (bool) ($eloResult['isDominant'] ?? false);

        $gameMode = $match->getGameMode();

        $winnerProfile->applyEloDelta($winnerDelta, $loserProfile->getName(), true, $gameMode);
        $loserProfile->applyEloDelta($loserDelta,   $winnerProfile->getName(), false, $gameMode);

        $winnerProfile->recordWin($gameMode, $match->getDuration());
        $loserProfile->recordLoss($gameMode, $match->getDuration());

        $winnerProfile->recordKill($gameMode);
        $loserProfile->recordDeath($gameMode);

        $result = new MatchResult(
            matchId:          $matchId,
            gameMode:         $gameMode,
            winnerUuid:       $winnerUuid,
            loserUuid:        $loserProfile->getUuid(),
            durationSeconds:  $match->getDuration(),
            playerSnapshots:  $snapshots,
        );

        $this->matchRepository->logMatch(
            matchId:  $matchId,
            gameMode: $gameMode,
            winner:   $winnerProfile->getName(),
            loser:    $loserProfile->getName(),
            duration: $match->getDuration(),
        );

        $this->webhookDispatcher->dispatch('match_finished', [
            'match_id'      => $matchId,
            'game_mode'     => $gameMode,
            'winner'        => ['uuid' => $winnerUuid,               'name' => $winnerProfile->getName(), 'elo_delta' => $winnerDelta],
            'loser'         => ['uuid' => $loserProfile->getUuid(),  'name' => $loserProfile->getName(),  'elo_delta' => $loserDelta],
            'duration'      => $match->getDuration(),
            'dominant_win'  => $isDominant,
        ]);

        $this->cleanup($matchId, $winnerUuid, $loserProfile->getUuid());

        return $result;
    }

    // -----------------------------------------------------------------------
    // Odczyt stanu
    // -----------------------------------------------------------------------

    public function getMatch(string $matchId): ?\Domain\Match\GameMatch {
        return $this->activeMatches[$matchId] ?? null;
    }

    public function getMatchByPlayer(string $uuid): ?\Domain\Match\GameMatch {
        $matchId = $this->playerMatchIndex[$uuid] ?? null;
        if ($matchId === null) return null;
        return $this->activeMatches[$matchId] ?? null;
    }

    public function isInMatch(string $uuid): bool {
        return isset($this->playerMatchIndex[$uuid]);
    }

    public function getActiveMatchCount(): int {
        return count($this->activeMatches);
    }

    /** @return array<string, \Domain\Match\GameMatch> */
    public function getAllActiveMatches(): array {
        return $this->activeMatches;
    }

    private function cleanup(string $matchId, string $uuidA, string $uuidB): void {
        unset(
            $this->activeMatches[$matchId],
            $this->playerMatchIndex[$uuidA],
            $this->playerMatchIndex[$uuidB],
        );
    }

    private function generateMatchId(): string {
        return 'match_' . bin2hex(random_bytes(8));
    }
}