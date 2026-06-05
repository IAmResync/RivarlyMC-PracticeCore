<?php

declare(strict_types=1);

namespace Domain\Match;

/**
 * Encja reprezentująca pojedynczą instancję meczu pomiędzy graczami.
 * Przechowuje informacje o trybie gry, uczestnikach oraz aktualnym wyniku.
 * Jest sercem logiki walki i zarządza czasem trwania starcia.
 *
 * Match jest trzymany wyłącznie w RAM podczas trwania rozgrywki.
 * Po zakończeniu jest przekształcany w MatchResult i zapisywany do DB.
 */
final class Match {

    private string $matchId;
    private string $gameMode;
    private MatchState $state;

    /** @var array<string, string> playerUuid => playerName */
    private array $players;

    private int $startedAt;
    private int $endedAt;
    private ?string $winnerUuid = null;
    private ?string $arenaId = null;

    /**
     * @param array<string, string> $players playerUuid => playerName
     */
    public function __construct(
        string $matchId,
        string $gameMode,
        array $players,
        ?string $arenaId = null,
    ) {
        $this->matchId = $matchId;
        $this->gameMode = $gameMode;
        $this->players = $players;
        $this->state = MatchState::WAITING;
        $this->startedAt = 0;
        $this->endedAt = 0;
        $this->arenaId = $arenaId;
    }

    public function getMatchId(): string {
        return $this->matchId;
    }

    public function getGameMode(): string {
        return $this->gameMode;
    }

    public function getState(): MatchState {
        return $this->state;
    }

    /**
     * @return array<string, string>
     */
    public function getPlayers(): array {
        return $this->players;
    }

    public function getStartedAt(): int {
        return $this->startedAt;
    }

    public function getEndedAt(): int {
        return $this->endedAt;
    }

    public function getWinnerUuid(): ?string {
        return $this->winnerUuid;
    }

    public function getArenaId(): ?string {
        return $this->arenaId;
    }

    public function setState(MatchState $state): void {
        $this->state = $state;
    }

    public function start(): void {
        $this->state = MatchState::ACTIVE;
        $this->startedAt = time();
    }

    public function end(string $winnerUuid): void {
        $this->state = MatchState::ENDING;
        $this->winnerUuid = $winnerUuid;
        $this->endedAt = time();
    }

    public function getDuration(): int {
        if ($this->startedAt === 0) {
            return 0;
        }
        $end = $this->endedAt !== 0 ? $this->endedAt : time();
        return $end - $this->startedAt;
    }

    public function isFinished(): bool {
        return $this->state === MatchState::ENDING;
    }

    public function isActive(): bool {
        return $this->state === MatchState::ACTIVE;
    }

    public function isWaiting(): bool {
        return $this->state === MatchState::WAITING;
    }

    public function isStarting(): bool {
        return $this->state === MatchState::STARTING;
    }

    /**
     * Zwraca UUID przeciwnika danego gracza (zakłada format 1v1).
     */
    public function getOpponentUuid(string $playerUuid): ?string {
        foreach ($this->players as $uuid => $name) {
            if ($uuid !== $playerUuid) {
                return $uuid;
            }
        }
        return null;
    }

    /**
     * Sprawdza czy gracz jest uczestnikiem meczu.
     */
    public function hasPlayer(string $playerUuid): bool {
        return isset($this->players[$playerUuid]);
    }

    // -----------------------------------------------------------------------
    // Serializacja (snapshot dla logów / eksportu – Match NIE jest persystowany
    // bezpośrednio; po zakończeniu przechodzi w MatchResult zapisywany do DB)
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'match_id'   => $this->matchId,
            'game_mode'  => $this->gameMode,
            'state'      => $this->state->value,
            'players'    => $this->players,
            'arena_id'   => $this->arenaId,
            'started_at' => $this->startedAt,
            'ended_at'   => $this->endedAt,
            'winner'     => $this->winnerUuid,
        ];
    }

    /**
     * Odtwarza Match z array (np. przy crash-recovery z cache).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self {
        $match = new self(
            matchId: (string) $data['match_id'],
            gameMode: (string) $data['game_mode'],
            players: (array) $data['players'],
            arenaId: isset($data['arena_id']) ? (string) $data['arena_id'] : null,
        );
        $match->state      = MatchState::from((string) $data['state']);
        $match->startedAt  = (int) $data['started_at'];
        $match->endedAt    = (int) $data['ended_at'];
        $match->winnerUuid = isset($data['winner']) ? (string) $data['winner'] : null;
        return $match;
    }
}
