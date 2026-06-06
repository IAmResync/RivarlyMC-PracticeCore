<?php

declare(strict_types=1);

namespace Domain\Match;

/**
 * Encja reprezentująca aktywną instancję meczu (pojedynku) w pamięci RAM.
 * Odpowiada za cykl życia walki, zarządzanie graczami oraz widzami (spectators).
 * W pełni zsynchronizowana z profilami NoMercy.
 */
final class GameMatch {

    private string $matchId;
    private string $gameMode;
    private MatchState $state;

    /** @var array<string, string> playerUuid => playerName (Główni gracze walczący) */
    private array $players;

    /** @var array<string, string> playerUuid => playerName (Widzowie na arenie) */
    private array $spectators = [];

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
        ?string $arenaId = null
    ) {
        $this->matchId = $matchId;
        $this->gameMode = $gameMode;
        $this->players = $players;
        $this->state = MatchState::WAITING;
        $this->startedAt = 0;
        $this->endedAt = 0;
        $this->arenaId = $arenaId;
    }

    // -----------------------------------------------------------------------
    // Klasyczne gettery NoMercy
    // -----------------------------------------------------------------------

    public function getMatchId(): string { return $this->matchId; }
    public function getGameMode(): string { return $this->gameMode; }
    public function getState(): MatchState { return $this->state; }
    /** @return array<string, string> */
    public function getPlayers(): array { return $this->players; }
    /** @return array<string, string> */
    public function getSpectators(): array { return $this->spectators; }
    public function getStartedAt(): int { return $this->startedAt; }
    public function getEndedAt(): int { return $this->endedAt; }
    public function getWinnerUuid(): ?string { return $this->winnerUuid; }
    public function getArenaId(): ?string { return $this->arenaId; }

    // -----------------------------------------------------------------------
    // Zarządzanie stanem i czasem meczu
    // -----------------------------------------------------------------------

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

    /**
     * Zwraca czas trwania walki w sekundach.
     */
    public function getDuration(): int {
        if ($this->startedAt === 0) {
            return 0;
        }
        $end = $this->endedAt !== 0 ? $this->endedAt : time();
        return $end - $this->startedAt;
    }

    public function isFinished(): bool { return $this->state === MatchState::ENDING; }
    public function isActive(): bool { return $this->state === MatchState::ACTIVE; }
    public function isWaiting(): bool { return $this->state === MatchState::WAITING; }
    public function isStarting(): bool { return $this->state === MatchState::STARTING; }

    // -----------------------------------------------------------------------
    // Logika relacji między graczami (1v1)
    // -----------------------------------------------------------------------

    /**
     * Sprawdza, czy dany gracz bierze czynny udział w walce.
     */
    public function hasPlayer(string $playerUuid): bool {
        return isset($this->players[$playerUuid]);
    }

    /**
     * Zwraca UUID przeciwnika dla podanego gracza.
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
     * Zwraca nick przeciwnika dla podanego gracza. Przydatne do komunikatów.
     */
    public function getOpponentName(string $playerUuid): ?string {
        $opponentUuid = $this->getOpponentUuid($playerUuid);
        return $opponentUuid !== null ? $this->players[$opponentUuid] : null;
    }

    // -----------------------------------------------------------------------
    // Obsługa systemu Spectatorów (Widzów)
    // -----------------------------------------------------------------------

    public function addSpectator(string $uuid, string $name): void {
        if (!isset($this->players[$uuid])) {
            $this->spectators[$uuid] = $name;
        }
    }

    public function removeSpectator(string $uuid): void {
        unset($this->spectators[$uuid]);
    }

    public function hasSpectator(string $uuid): bool {
        return isset($this->spectators[$uuid]);
    }

    // -----------------------------------------------------------------------
    // Awaryjna serializacja (np. do dumpowania stanu meczów w przypadku crashu)
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'match_id'   => $this->matchId,
            'game_mode'  => $this->gameMode,
            'state'      => $this->state->value,
            'players'    => $this->players,
            'spectators' => $this->spectators,
            'arena_id'   => $this->arenaId,
            'started_at' => $this->startedAt,
            'ended_at'   => $this->endedAt,
            'winner'     => $this->winnerUuid,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        $match = new self(
            matchId: (string) $data['match_id'],
            gameMode: (string) $data['game_mode'],
            players: (array) $data['players'],
            arenaId: isset($data['arena_id']) ? (string) $data['arena_id'] : null,
        );

        $stateValue = (string) ($data['state'] ?? 'WAITING');
        $match->state = MatchState::tryFrom($stateValue) ?? MatchState::WAITING;

        $match->spectators = (array) ($data['spectators'] ?? []);
        $match->startedAt  = (int) $data['started_at'];
        $match->endedAt    = (int) $data['ended_at'];
        $match->winnerUuid = isset($data['winner']) ? (string) $data['winner'] : null;

        return $match;
    }
}