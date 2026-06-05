<?php

declare(strict_types=1);

namespace Rematch;

use Application\Match\MatchManager;
use Application\Matchmaking\MatchPair;
use Domain\Player\PlayerProfile;

/**
 * TODO: Obsługuje cykl życia propozycji rewanżu po zakończonym meczu.
 * Automatycznie wysyła propozycję obu graczom i czeka 15 sekund na akceptację.
 * Przy akceptacji koordynuje z MatchManager aby stworzyć natychmiastowy rematch.
 */
final class RematchManager {

    /**
     * Aktywne zaproszenia rewanżowe indeksowane kluczem "rematch:{playerA}:{playerB}"
     * @var array<string, RematchRequest>
     */
    private array $pending = [];

    public function __construct(
        private readonly MatchManager $matchManager
    ) {}

    // -----------------------------------------------------------------------
    // Rejestracja automatycznej propozycji po meczu
    // -----------------------------------------------------------------------

    /**
     * Automatycznie generuje propozycję rewanżu dla obu graczy po zakończeniu walki.
     */
    public function offerRematch(
        PlayerProfile $playerA,
        PlayerProfile $playerB,
        string        $gameMode
    ): RematchRequest {
        $request = new RematchRequest(
            senderUuid:   $playerA->getUuid(),
            senderName:   $playerA->getName(),
            receiverUuid: $playerB->getUuid(),
            receiverName: $playerB->getName(),
            gameMode:     $gameMode
        );

        // Zapisujemy klucz w spójnym formacie
        $this->pending[$request->getKey()] = $request;

        return $request;
    }

    // -----------------------------------------------------------------------
    // Akceptacja rewanżu
    // -----------------------------------------------------------------------

    /**
     * Jeden z graczy akceptuje automatyczną propozycję rewanżu.
     */
    public function acceptRematch(
        PlayerProfile $requester,
        PlayerProfile $opponent
    ): RematchResult {
        // Ponieważ klucz może być zapisany jako A:B lub B:A, sprawdzamy obie kombinacje
        $key = RematchRequest::makeKey($requester->getUuid(), $opponent->getUuid());
        $request = $this->pending[$key] ?? null;

        if ($request === null) {
            $key = RematchRequest::makeKey($opponent->getUuid(), $requester->getUuid());
            $request = $this->pending[$key] ?? null;
        }

        if ($request === null) {
            return RematchResult::fail(RematchFailReason::REQUEST_NOT_FOUND);
        }

        if ($request->isExpired()) {
            unset($this->pending[$key]);
            return RematchResult::fail(RematchFailReason::REQUEST_EXPIRED);
        }

        // Obaj gracze muszą znajdować się w czystym lobby, by ponowić walkę
        if (!$opponent->isInLobby()) {
            unset($this->pending[$key]);
            return RematchResult::fail(RematchFailReason::OPPONENT_NOT_IN_LOBBY);
        }

        if (!$requester->isInLobby()) {
            return RematchResult::fail(RematchFailReason::REQUESTER_NOT_IN_LOBBY);
        }

        unset($this->pending[$key]);

        // Pobieramy ELO (wymagane w konstruktorze MatchPair na PM5!)
        $requesterElo = method_exists($requester, 'getElo') ? $requester->getElo($request->gameMode) : 1000;
        $opponentElo = method_exists($opponent, 'getElo') ? $opponent->getElo($request->gameMode) : 1000;

        // Tworzenie poprawnego obiektu MatchPair z pełnymi danymi ELO
        $pair = new MatchPair(
            playerAUuid: $requester->getUuid(),
            playerAName: $requester->getName(),
            playerAElo: (int) $requesterElo,
            playerBUuid: $opponent->getUuid(),
            playerBName: $opponent->getName(),
            playerBElo: (int) $opponentElo,
            gameMode: $request->gameMode
        );

        // Natychmiastowy start meczu przez MatchManager
        $match = $this->matchManager->createMatch($pair, $requester, $opponent);

        return RematchResult::ok($match->getMatchId());
    }

    // -----------------------------------------------------------------------
    // Manualne odrzucenie propozycji
    // -----------------------------------------------------------------------

    public function declineRematch(
        PlayerProfile $requester,
        PlayerProfile $opponent
    ): RematchResult {
        $key = RematchRequest::makeKey($requester->getUuid(), $opponent->getUuid());
        if (!isset($this->pending[$key])) {
            $key = RematchRequest::makeKey($opponent->getUuid(), $requester->getUuid());
        }

        if (!isset($this->pending[$key])) {
            return RematchResult::fail(RematchFailReason::REQUEST_NOT_FOUND);
        }

        unset($this->pending[$key]);
        return RematchResult::ok();
    }

    // -----------------------------------------------------------------------
    // Czyszczenie pamięci
    // -----------------------------------------------------------------------

    public function purgeExpired(): int {
        $before = count($this->pending);
        $this->pending = array_filter($this->pending, fn(RematchRequest $r) => !$r->isExpired());
        return $before - count($this->pending);
    }

    public function removeAllFor(string $uuid): void {
        $this->pending = array_filter(
            $this->pending,
            fn(RematchRequest $r) => $r->senderUuid !== $uuid && $r->receiverUuid !== $uuid
        );
    }

    public function getPendingRequestFor(string $uuid): ?RematchRequest {
        foreach ($this->pending as $request) {
            if ($request->senderUuid === $uuid || $request->receiverUuid === $uuid) {
                return $request;
            }
        }
        return null;
    }
}

// ---------------------------------------------------------------------------
// Obiekty pomocnicze (Result DTO & Fail Reasons)
// ---------------------------------------------------------------------------

final class RematchResult {

    private function __construct(
        public readonly bool               $success,
        public readonly ?RematchFailReason $reason  = null,
        public readonly ?string            $matchId = null
    ) {}

    public static function ok(?string $matchId = null): self {
        return new self(true, null, $matchId);
    }

    public static function fail(RematchFailReason $reason): self {
        return new self(false, $reason);
    }
}

enum RematchFailReason {
    case REQUEST_NOT_FOUND;
    case REQUEST_EXPIRED;
    case OPPONENT_NOT_IN_LOBBY;
    case REQUESTER_NOT_IN_LOBBY;
}