<?php

declare(strict_types=1);

namespace Duel;

use Application\Match\MatchManager;
use Application\Matchmaking\MatchPair;
use Domain\Player\PlayerProfile;
use GameMode\GameModeRegistry;;

/**
 * TODO: Obsługuje wysyłanie, akceptowanie i odrzucanie prywatnych zaproszeń do walki.
 * Sprawdza, czy obaj gracze są w LOBBY i nie są już w kolejce lub meczu.
 * Po akceptacji przekazuje parę bezpośrednio do MatchManager z pominięciem kolejki.
 */
final class DuelManager {

    /**
     * Aktywne zaproszenia w pamięci, indeksowane kluczem "{senderUuid}:{receiverUuid}"
     * @var array<string, DuelRequest>
     */
    private array $pending = [];

    public function __construct(
        private readonly MatchManager $matchManager,
        private readonly GameModeRegistry $gameModeRegistry
    ) {}

    // -----------------------------------------------------------------------
    // Wysyłanie wyzwań
    // -----------------------------------------------------------------------

    public function sendRequest(
        PlayerProfile $sender,
        PlayerProfile $receiver,
        string        $gameMode
    ): DuelResult {
        if (!$this->gameModeRegistry->exists($gameMode)) {
            return DuelResult::fail(DuelFailReason::UNKNOWN_GAME_MODE);
        }

        if (!$sender->isInLobby()) {
            return DuelResult::fail(DuelFailReason::SENDER_NOT_IN_LOBBY);
        }

        if (!$receiver->isInLobby()) {
            return DuelResult::fail(DuelFailReason::RECEIVER_NOT_IN_LOBBY);
        }

        if (method_exists($receiver, 'isAcceptingDuels') && !$receiver->isAcceptingDuels()) {
            return DuelResult::fail(DuelFailReason::RECEIVER_NOT_ACCEPTING);
        }

        $key = DuelRequest::makeKey($sender->getUuid(), $receiver->getUuid());

        if (isset($this->pending[$key]) && !$this->pending[$key]->isExpired()) {
            return DuelResult::fail(DuelFailReason::ALREADY_PENDING);
        }

        $this->pending[$key] = new DuelRequest(
            senderUuid:   $sender->getUuid(),
            senderName:   $sender->getName(),
            receiverUuid: $receiver->getUuid(),
            receiverName: $receiver->getName(),
            gameMode:     $gameMode
        );

        return DuelResult::ok();
    }

    // -----------------------------------------------------------------------
    // Akceptowanie wyzwań (NAPRAWIONO BŁĄD MISSING ELO PARAMETERS)
    // -----------------------------------------------------------------------

    public function acceptRequest(
        PlayerProfile $receiver,
        PlayerProfile $sender
    ): DuelResult {
        $key = DuelRequest::makeKey($sender->getUuid(), $receiver->getUuid());
        $request = $this->pending[$key] ?? null;

        if ($request === null) {
            return DuelResult::fail(DuelFailReason::REQUEST_NOT_FOUND);
        }

        if ($request->isExpired()) {
            unset($this->pending[$key]);
            return DuelResult::fail(DuelFailReason::REQUEST_EXPIRED);
        }

        if (!$sender->isInLobby()) {
            unset($this->pending[$key]);
            return DuelResult::fail(DuelFailReason::SENDER_NOT_IN_LOBBY);
        }

        if (!$receiver->isInLobby()) {
            return DuelResult::fail(DuelFailReason::RECEIVER_NOT_IN_LOBBY);
        }

        unset($this->pending[$key]);

        // Dynamicznie wyciągamy ELO dla konkretnego trybu gry, lub ogólne (fallback do 1000 jeśli brak metody)
        $senderElo = method_exists($sender, 'getElo') ? $sender->getElo($request->gameMode) : 1000;
        $receiverElo = method_exists($receiver, 'getElo') ? $receiver->getElo($request->gameMode) : 1000;

        // Tworzenie MatchPair z uwzględnieniem wymaganych parametrów ELO z dymka błędu
        $pair = new MatchPair(
            playerAUuid: $sender->getUuid(),
            playerAName: $sender->getName(),
            playerAElo: (int) $senderElo,
            playerBUuid: $receiver->getUuid(),
            playerBName: $receiver->getName(),
            playerBElo: (int) $receiverElo,
            gameMode: $request->gameMode
        );

        $match = $this->matchManager->createMatch($pair, $sender, $receiver);

        return DuelResult::ok($match->getMatchId());
    }

    // -----------------------------------------------------------------------
    // Odrzucanie wyzwań
    // -----------------------------------------------------------------------

    public function declineRequest(
        PlayerProfile $receiver,
        PlayerProfile $sender
    ): DuelResult {
        $key = DuelRequest::makeKey($sender->getUuid(), $receiver->getUuid());

        if (!isset($this->pending[$key])) {
            return DuelResult::fail(DuelFailReason::REQUEST_NOT_FOUND);
        }

        unset($this->pending[$key]);
        return DuelResult::ok();
    }

    // -----------------------------------------------------------------------
    // Czyszczenie pamięci
    // -----------------------------------------------------------------------

    public function purgeExpired(): int {
        $before = count($this->pending);

        $this->pending = array_filter(
            $this->pending,
            fn(DuelRequest $r) => !$r->isExpired()
        );

        return $before - count($this->pending);
    }

    public function removeAllFor(string $uuid): void {
        $this->pending = array_filter(
            $this->pending,
            fn(DuelRequest $r) => $r->senderUuid !== $uuid && $r->receiverUuid !== $uuid
        );
    }

    public function getPendingRequestsFor(string $receiverUuid): array {
        return array_filter($this->pending, fn(DuelRequest $r) => $r->receiverUuid === $receiverUuid);
    }
}