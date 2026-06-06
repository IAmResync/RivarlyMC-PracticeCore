<?php

declare(strict_types=1);

namespace Domain\Tournament;

/**
 * Logiczne i deterministyczne drzewko drabinki turniejowej (Single Elimination).
 * Zarządza strukturą pucharową oraz wspiera pełny zapis stanu (persistence) NoMercy.
 */
final class Bracket {

    private string $tournamentId;
    private int $currentRound;
    private int $totalRounds;

    /** @var array<int, array<string, array{player1: ?string, player2: ?string, winner: ?string}>> Runda => MatchId => Statystyki walki */
    private array $matches;

    /** @var list<string> Lista UUID wszystkich graczy startujących w turnieju */
    private array $participants;

    /**
     * @param list<string> $participants
     */
    public function __construct(string $tournamentId, array $participants) {
        $this->tournamentId = $tournamentId;
        $this->participants = $participants;
        $this->currentRound = 1;
        $this->totalRounds = $this->calculateTotalRounds(count($participants));
        $this->matches = [];
        $this->generateInitialBracket();
    }

    // -----------------------------------------------------------------------
    // Bezpieczne gettery w stylu NoMercy
    // -----------------------------------------------------------------------

    public function getTournamentId(): string { return $this->tournamentId; }
    public function getCurrentRound(): int { return $this->currentRound; }
    public function getTotalRounds(): int { return $this->totalRounds; }
    /** @return list<string> */
    public function getParticipants(): array { return $this->participants; }
    /** @return array<int, array<string, array{player1: ?string, player2: ?string, winner: ?string}>> */
    public function getMatches(): array { return $this->matches; }

    /** @return array<string, array{player1: ?string, player2: ?string, winner: ?string}> */
    public function getRoundMatches(int $round): array { return $this->matches[$round] ?? []; }
    /** @return array<string, array{player1: ?string, player2: ?string, winner: ?string}> */
    public function getCurrentRoundMatches(): array { return $this->getRoundMatches($this->currentRound); }

    // -----------------------------------------------------------------------
    // Logika zarządzania turniejem
    // -----------------------------------------------------------------------

    /**
     * Rejestruje wynik meczu i przenosi zwycięzcę w dokładnie wyznaczone miejsce w strukturze drzewka.
     */
    public function recordMatchResult(string $matchId, string $winnerUuid): void {
        if (!isset($this->matches[$this->currentRound][$matchId])) {
            return;
        }

        $this->matches[$this->currentRound][$matchId]['winner'] = $winnerUuid;

        // Jeśli to nie jest finał, deterministycznie wylicz miejsce gracza w kolejnej rundzie
        if ($this->currentRound < $this->totalRounds) {
            $this->promoteToNextRound($matchId, $winnerUuid);
        }
    }

    /**
     * Sprawdza, czy wszystkie walki w obecnej rundzie dobiegły końca.
     */
    public function isRoundComplete(): bool {
        foreach ($this->getCurrentRoundMatches() as $match) {
            if ($match['winner'] === null && ($match['player1'] !== null || $match['player2'] !== null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Przechodzi do kolejnej rundy turnieju i automatycznie przetwarza puste losy (bye).
     */
    public function advanceToNextRound(): void {
        if (!$this->isRoundComplete() || $this->currentRound >= $this->totalRounds) {
            return;
        }

        $this->currentRound++;

        // Automatyczny awans dla graczy, którzy w nowej rundzie trafili na puste miejsce (bye)
        foreach ($this->getCurrentRoundMatches() as $matchId => $match) {
            if ($match['player1'] !== null && $match['player2'] === null) {
                $this->matches[$this->currentRound][$matchId]['winner'] = $match['player1'];
            } elseif ($match['player2'] !== null && $match['player1'] === null) {
                $this->matches[$this->currentRound][$matchId]['winner'] = $match['player2'];
            }
        }
    }

    public function isFinished(): bool {
        if ($this->currentRound < $this->totalRounds) {
            return false;
        }
        return $this->isRoundComplete();
    }

    public function getWinner(): ?string {
        if (!$this->isFinished()) {
            return null;
        }

        $finalMatches = $this->getRoundMatches($this->totalRounds);
        foreach ($finalMatches as $match) {
            if ($match['winner'] !== null) {
                return $match['winner'];
            }
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Algorytmy generowania struktury drzewka pucharowego
    // -----------------------------------------------------------------------

    private function calculateTotalRounds(int $participantCount): int {
        if ($participantCount <= 1) return 0;
        return (int) ceil(log($participantCount, 2));
    }

    private function generateInitialBracket(): void {
        $clonedParticipants = $this->participants;
        shuffle($clonedParticipants);

        $nextPowerOfTwo = $this->nextPowerOfTwo(count($clonedParticipants));
        while (count($clonedParticipants) < $nextPowerOfTwo) {
            $clonedParticipants[] = null; // Bye (wolny los)
        }

        // 1. Wygeneruj puste sloty dla absolutnie wszystkich rund turnieju (stałe drzewko)
        $roundMatchesCount = $nextPowerOfTwo / 2;
        for ($r = 1; $r <= $this->totalRounds; $r++) {
            $this->matches[$r] = [];
            for ($m = 0; $m < $roundMatchesCount; $m++) {
                $mId = $this->generateMatchId($r, $m);
                $this->matches[$r][$mId] = ['player1' => null, 'player2' => null, 'winner' => null];
            }
            $roundMatchesCount /= 2;
        }

        // 2. Wypełnij pierwszą rundę wygenerowanymi graczami
        $matchIndex = 0;
        for ($i = 0; $i < count($clonedParticipants); $i += 2) {
            $player1 = $clonedParticipants[$i];
            $player2 = $clonedParticipants[$i + 1] ?? null;
            $matchId = $this->generateMatchId(1, $matchIndex);

            $winner = null;
            if ($player1 === null && $player2 !== null) $winner = $player2;
            if ($player2 === null && $player1 !== null) $winner = $player1;

            $this->matches[1][$matchId] = [
                'player1' => $player1,
                'player2' => $player2,
                'winner'  => $winner
            ];

            // Jeśli ktoś dostał wolny los (bye), od razu promuj go wyżej w drzewku
            if ($winner !== null && $this->totalRounds > 1) {
                $this->promoteToNextRound($matchId, $winner);
            }

            $matchIndex++;
        }
    }

    /**
     * Deterministycznie awansuje gracza do następnej rundy na podstawie ID ukończonego meczu.
     */
    private function promoteToNextRound(string $currentMatchId, string $playerUuid): void {
        // Parsuje format "ID-r{runda}-m{indeks}"
        if (preg_match('/-r(\d+)-m(\d+)$/', $currentMatchId, $matches) !== 1) {
            return;
        }

        $currentRound = (int) $matches[1];
        $currentMatchIndex = (int) $matches[2];

        $nextRound = $currentRound + 1;
        $nextMatchIndex = (int) floor($currentMatchIndex / 2);
        $nextMatchId = $this->generateMatchId($nextRound, $nextMatchIndex);

        if (!isset($this->matches[$nextRound][$nextMatchId])) {
            return;
        }

        // Jeśli indeks obecnego meczu był parzysty -> wpada na slot player1. Jeśli nieparzysty -> na slot player2.
        if ($currentMatchIndex % 2 === 0) {
            $this->matches[$nextRound][$nextMatchId]['player1'] = $playerUuid;
        } else {
            $this->matches[$nextRound][$nextMatchId]['player2'] = $playerUuid;
        }
    }

    private function generateMatchId(int $round, int $index): string {
        return sprintf("%s-r%d-m%d", $this->tournamentId, $round, $index);
    }

    private function nextPowerOfTwo(int $n): int {
        if ($n <= 1) return 2;
        return 1 << (int) ceil(log($n, 2));
    }

    // -----------------------------------------------------------------------
    // Serializacja i Baza Danych (Crash-recovery systemu turniejowego)
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'tournament_id' => $this->tournamentId,
            'current_round' => $this->currentRound,
            'total_rounds'  => $this->totalRounds,
            'participants'  => $this->participants,
            'matches'       => $this->matches
        ];
    }

    /** @param array<string, mixed> $data
     * @throws \ReflectionException
     */
    public static function fromArray(array $data): object|string
    {
        // Wykorzystujemy ukryty konstruktor bez wywoływania algorytmu generowania na nowo
        $bracket = VicarReflector::blankInstance(self::class);

        $bracket->tournamentId = (string) $data['tournament_id'];
        $bracket->currentRound = (int) $data['current_round'];
        $bracket->totalRounds  = (int) $data['total_rounds'];
        $bracket->participants = (array) $data['participants'];
        $bracket->matches      = (array) $data['matches'];

        return $bracket;
    }
}

/**
 * Mały, wewnętrzny helper NoMercy pozwalający ominąć czyszczenie konstruktora przy odczycie z bazy.
 */
class VicarReflector {
    /**
     * @throws \ReflectionException
     */
    public static function blankInstance(string $class): object {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}