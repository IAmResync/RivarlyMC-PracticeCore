<?php

declare(strict_types=1);

namespace Application\Tournament;

/**
 * Generuje i zarządza drabinką single-elimination dla turnieju.
 * Bezpieczna dla PHP 8+ - brak konfliktów nazw strukturalnych.
 */
final class BracketGenerator {

    // -----------------------------------------------------------------------
    // Generowanie drabinki
    // -----------------------------------------------------------------------

    /**
     * Tworzy nową drabinkę z listy UUID graczy.
     * Uzupełnia wolnymi losami (BYE) do następnej potęgi 2.
     *
     * @param list<string> $playerUuids  UUID graczy – minimum 2, maksimum 64
     * @param list<string> $playerNames  Nazwy (indeks = indeks UUID)
     * @param bool         $shuffle      Czy losować kolejność przed sparowaniem
     */
    public function generate(
        array $playerUuids,
        array $playerNames,
        bool  $shuffle = true,
    ): GeneratedBracket {
        if (count($playerUuids) < 2) {
            throw new \InvalidArgumentException('Minimum 2 players required.');
        }

        if ($shuffle) {
            $combined = array_map(null, $playerUuids, $playerNames);
            shuffle($combined);
            $playerUuids = array_column($combined, 0);
            $playerNames = array_column($combined, 1);
        }

        $size     = $this->nextPowerOfTwo(count($playerUuids));
        $byeCount = $size - count($playerUuids);

        // Uzupełnij prawdziwych graczy
        $slots = [];
        foreach ($playerUuids as $index => $uuid) {
            $slots[] = new BracketSlot($uuid, $playerNames[$index]);
        }

        // Uzupełnij wolne losy (BYE) na końcu stawki
        for ($i = 0; $i < $byeCount; $i++) {
            $slots[] = BracketSlot::bye();
        }

        // Runda 1 – sparuj sąsiadów w strukturze
        $round1 = [];
        for ($i = 0; $i < $size; $i += 2) {
            $round1[] = new BracketMatch(
                id:       $this->matchId(1, $i / 2),
                round:    1,
                position: $i / 2,
                slotA:    $slots[$i],
                slotB:    $slots[$i + 1],
            );
        }

        $totalRounds = (int) log($size, 2);

        // Zbuduj puste rundy 2..N (wypełniane automatycznie przez advanceWinner)
        $rounds = [$round1];
        $matchesInRound = $size / 4;

        for ($r = 2; $r <= $totalRounds; $r++) {
            $emptyRound = [];
            for ($p = 0; $p < $matchesInRound; $p++) {
                $emptyRound[] = new BracketMatch(
                    id:       $this->matchId($r, $p),
                    round:    $r,
                    position: $p,
                    slotA:    BracketSlot::pending(),
                    slotB:    BracketSlot::pending(),
                );
            }
            $rounds[]       = $emptyRound;
            $matchesInRound = max(1, $matchesInRound / 2);
        }

        $bracket = new GeneratedBracket($rounds, $totalRounds, $size);

        // Wywołujemy kaskadowe sprawdzanie BYE dla pierwszej rundy
        foreach ($round1 as $match) {
            if ($match->slotA->isBye()) {
                $bracket->advanceWinner($match->id, $match->slotB);
            } elseif ($match->slotB->isBye()) {
                $bracket->advanceWinner($match->id, $match->slotA);
            }
        }

        return $bracket;
    }

    // -----------------------------------------------------------------------
    // Awansowanie zwycięzcy
    // -----------------------------------------------------------------------

    /**
     * Zapisuje zwycięzcę meczu i wstawia go do odpowiedniego miejsca w następnej rundzie.
     */
    public function recordWinner(
        GeneratedBracket $bracket,
        string           $matchId,
        string           $winnerUuid,
        string           $winnerName,
    ): void {
        $match = $bracket->getMatch($matchId);

        if ($match === null) {
            throw new \InvalidArgumentException("Unknown match ID: {$matchId}");
        }

        $winner = new BracketSlot($winnerUuid, $winnerName);
        $bracket->advanceWinner($matchId, $winner);
    }

    // -----------------------------------------------------------------------
    // Wizualizacja chat (Unicode)
    // -----------------------------------------------------------------------

    public function render(GeneratedBracket $bracket): string {
        $lines = ["§9§l[ NO-MERCY TOURNAMENT BRACKET ]§r", ""];

        foreach ($bracket->getRounds() as $roundIndex => $matches) {
            $roundNum = $roundIndex + 1;
            $label    = $roundNum === $bracket->getTotalRounds()
                ? "§b⚔ GRAND FINAL"
                : "§f Round {$roundNum}";

            $lines[] = $label;
            $lines[] = "§9" . str_repeat("─", 32);

            foreach ($matches as $match) {
                $a = $match->slotA->isBye()      ? "§8[Wolny Los]"
                    : ($match->slotA->isPending() ? "§7[Oczekiwanie...]"
                        : "§f" . $match->slotA->name);

                $b = $match->slotB->isBye()      ? "§8[Wolny Los]"
                    : ($match->slotB->isPending() ? "§7[Oczekiwanie...]"
                        : "§f" . $match->slotB->name);

                $winner = $match->winner !== null
                    ? " §b→ §l" . $match->winner->name . ($roundNum === $bracket->getTotalRounds() ? " 🏆" : "")
                    : "";

                $lines[] = "  §9┌ {$a}";
                $lines[] = "  §9└ {$b}{$winner}";
                $lines[] = "";
            }
        }

        return implode("\n", $lines);
    }

    private function nextPowerOfTwo(int $n): int {
        $power = 1;
        while ($power < $n) {
            $power *= 2;
        }
        return $power;
    }

    private function matchId(int $round, int $position): string {
        return "r{$round}_m{$position}";
    }
}

// ---------------------------------------------------------------------------
// Szczelne i zoptymalizowane Value Objecty domenowe
// ---------------------------------------------------------------------------

final class BracketSlot {

    public function __construct(
        public readonly ?string $uuid,
        public readonly ?string $name,
        private readonly bool   $isBye = false,
        private readonly bool   $isPending = false,
    ) {}

    public static function bye(): self {
        return new self(null, null, true, false);
    }

    public static function pending(): self {
        return new self(null, null, false, true);
    }

    public function isBye(): bool     { return $this->isBye; }
    public function isPending(): bool { return $this->isPending; }
    public function isReal(): bool    { return !$this->isBye && !$this->isPending; }
}

final class BracketMatch {

    public ?BracketSlot $winner = null;

    public function __construct(
        public readonly string $id,
        public readonly int    $round,
        public readonly int    $position,
        public BracketSlot     $slotA,
        public BracketSlot     $slotB,
    ) {}

    public function isReady(): bool {
        return $this->slotA->isReal() && $this->slotB->isReal();
    }

    public function isFinished(): bool {
        return $this->winner !== null;
    }
}

final class GeneratedBracket {

    /** @var array<string, BracketMatch> matchId => BracketMatch */
    private array $matchIndex = [];

    /**
     * @param list<list<BracketMatch>> $rounds
     */
    public function __construct(
        private array $rounds,
        private int   $totalRounds,
        private int   $bracketSize,
    ) {
        foreach ($rounds as $round) {
            foreach ($round as $match) {
                $this->matchIndex[$match->id] = $match;
            }
        }
    }

    public function getMatch(string $id): ?BracketMatch {
        return $this->matchIndex[$id] ?? null;
    }

    /** @return list<list<BracketMatch>> */
    public function getRounds(): array {
        return $this->rounds;
    }

    public function getTotalRounds(): int  { return $this->totalRounds; }
    public function getBracketSize(): int  { return $this->bracketSize; }

    /**
     * KASKADOWE AWANSOWANIE: Automatycznie przepycha graczy dalej,
     * jeśli w kolejnych etapach również trafiają na sloty typu BYE.
     */
    public function advanceWinner(string $matchId, BracketSlot $winner): void {
        $match = $this->matchIndex[$matchId] ?? null;
        if ($match === null) return;

        $match->winner = $winner;

        $nextRoundIndex = $match->round;
        if (!isset($this->rounds[$nextRoundIndex])) return; // Finał osiągnięty

        $nextPosition = (int) floor($match->position / 2);
        $nextMatch    = $this->rounds[$nextRoundIndex][$nextPosition] ?? null;
        if ($nextMatch === null) return;

        // Przypisz gracza do odpowiedniego slotu w kolejnej rundzie
        if ($match->position % 2 === 0) {
            $nextMatch->slotA = $winner;
        } else {
            $nextMatch->slotB = $winner;
        }

        // --- KLUCZOWE FIX TODO ---
        // Jeśli nowo zaktualizowany mecz ma z drugiej strony BYE, automatycznie awansuj gracza wyżej!
        if ($nextMatch->slotA->isReal() && $nextMatch->slotB->isBye()) {
            $this->advanceWinner($nextMatch->id, $nextMatch->slotA);
        } elseif ($nextMatch->slotB->isReal() && $nextMatch->slotA->isBye()) {
            $this->advanceWinner($nextMatch->id, $nextMatch->slotB);
        }
    }

    /**
     * @return list<BracketMatch>
     */
    public function getReadyMatches(): array {
        $ready = [];
        foreach ($this->matchIndex as $match) {
            if ($match->isReady() && !$match->isFinished()) {
                $ready[] = $match;
            }
        }
        return $ready;
    }

    public function isFinished(): bool {
        $finalRound = end($this->rounds);
        return isset($finalRound[0]) && $finalRound[0]->isFinished();
    }

    public function getWinner(): ?BracketSlot {
        $finalRound = end($this->rounds);
        return isset($finalRound[0]) ? $finalRound[0]->winner : null;
    }
}