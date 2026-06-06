<?php

declare(strict_types=1);

namespace Domain\Ranking;

/**
 * Obiekt przechowujący wyniki obliczeń zmiany punktów ELO.
 * Autorska implementacja zintegrowana z bezpiecznymi getterami NoMercy.
 */
final class EloChangeResult {

    private int $winnerDelta;
    private int $loserDelta;
    private bool $isDominantWin;
    private float $winnerExpected;
    private float $loserExpected;

    public function __construct(
        int $winnerDelta,
        int $loserDelta,
        bool $isDominantWin,
        float $winnerExpected,
        float $loserExpected
    ) {
        $this->winnerDelta = $winnerDelta;
        $this->loserDelta = $loserDelta;
        $this->isDominantWin = $isDominantWin;
        $this->winnerExpected = $winnerExpected;
        $this->loserExpected = $loserExpected;
    }

    // -----------------------------------------------------------------------
    // Bezpieczne gettery dla reszty systemów
    // -----------------------------------------------------------------------

    public function getWinnerDelta(): int {
        return $this->winnerDelta;
    }

    public function getLoserDelta(): int {
        return $this->loserDelta;
    }

    public function isDominantWin(): bool {
        return $this->isDominantWin;
    }

    public function getWinnerExpected(): float {
        return $this->winnerExpected;
    }

    public function getLoserExpected(): float {
        return $this->loserExpected;
    }

    /**
     * Czy zwycięzca był faworytem przed meczem (miał wyższe szanse)?
     */
    public function winnerWasFavored(): bool {
        return $this->winnerExpected > 0.5;
    }

    /**
     * Czy to była niespodzianka (upset) – czyli wygrał gracz z mniejszym ELO?
     */
    public function wasUpset(): bool {
        return $this->winnerExpected < 0.5;
    }

    /**
     * Zwraca sformatowany tekst dla zwycięzcy, np. "+18".
     */
    public function getFormattedWinnerDelta(): string {
        return '+' . $this->winnerDelta;
    }

    /**
     * Zwraca sformatowany tekst dla przegranego, np. "-14".
     */
    public function getFormattedLoserDelta(): string {
        return (string) $this->loserDelta;
    }

    /** * Eksport danych wyniku do tablicy.
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'winner_delta'    => $this->winnerDelta,
            'loser_delta'     => $this->loserDelta,
            'is_dominant_win' => $this->isDominantWin,
            'winner_expected' => $this->winnerExpected,
            'loser_expected'  => $this->loserExpected,
        ];
    }
}