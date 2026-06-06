<?php

declare(strict_types=1);

namespace Domain\Ranking;

use Domain\Player\PlayerProfile;

/**
 * Autorski, zaawansowany algorytm obliczania punktów ELO dostosowany do formatu 1v1.
 * W pełni zintegrowany z obiektami PlayerProfile systemu NoMercy.
 */
final class EloCalculator {

    // K-Factor: Waga zmian punktowych (im większa, tym szybsza zmiana rankingu)
    private const K_NEW_PLAYER   = 40;   // Dla nowych graczy (< 20 meczów) - szybka kalibracja
    private const K_STANDARD     = 30;   // Standardowa rozgrywka (< 2200 ELO)
    private const K_HIGH_ELO     = 20;   // Dla elity (≥ 2200 ELO) - stabilizacja topki

    private const NEW_PLAYER_THRESHOLD = 20;
    private const HIGH_ELO_THRESHOLD   = 2200;

    // System nagród za szybkie walki (Dominant Win / Loss)
    private const DOMINANT_WIN_TIME_SEC  = 60;   // Walka wygrana poniżej minuty to Dominacja
    private const DOMINANT_BONUS         = 0.10; // +10% punktów dla wygranego
    private const DOMINANT_LOSS_REDUCTION = 0.10; // -10% straty dla przegranego (szybka porażka)

    private const MIN_ELO_GAIN  = 1;    // Zawsze dostaniesz minimum 1 punkt za wygraną
    private const MAX_ELO_DELTA = 60;   // Maksymalny limit punktów do zdobycia/stracenia w jednej walce

    /**
     * Główna metoda obliczająca zmianę ELO po zakończonym pojedynku.
     * Przekazujesz całe profile, kalkulator sam zajmuje się resztą!
     *
     * @return array{winnerGain: int, loserLoss: int, isDominant: bool}
     */
    public static function calculate(
        PlayerProfile $winner,
        PlayerProfile $loser,
        int $matchDurationSec = 300
    ): array {

        $winnerElo = $winner->getGlobalElo();
        $loserElo = $loser->getGlobalElo();

        // 1. Obliczanie prawdopodobieństwa wygranej (wzór matematyczny ELO)
        $expectedWinner = self::expectedScore($winnerElo, $loserElo);
        $expectedLoser  = 1.0 - $expectedWinner;

        // 2. Dobór dynamicznego K-Factor dla każdego z graczy
        $kWinner = self::kFactor($winnerElo, $winner->getTotalMatchesPlayed());
        $kLoser  = self::kFactor($loserElo, $loser->getTotalMatchesPlayed());

        // 3. Wyznaczenie bazowej zmiany punktowej
        $rawGain = $kWinner * (1.0 - $expectedWinner);
        $rawLoss = $kLoser  * (0.0 - $expectedLoser);

        // 4. Sprawdzenie warunku Dominacji (szybka walka)
        $isDominant = $matchDurationSec <= self::DOMINANT_WIN_TIME_SEC;
        if ($isDominant) {
            $rawGain *= (1.0 + self::DOMINANT_BONUS);
            $rawLoss *= (1.0 - self::DOMINANT_LOSS_REDUCTION);
        }

        // 5. Zaokrąglenia i zabezpieczenia progów minimalnych/maksymalnych
        $gainRounded = self::clampDelta((int) round($rawGain));
        $lossRounded = self::clampDelta((int) round(abs($rawLoss)));

        // Zwycięzca musi zyskać chociaż minimalny punkt
        $gainRounded = max(self::MIN_ELO_GAIN, $gainRounded);

        return [
            'winnerGain' => $gainRounded,
            'loserLoss'  => $lossRounded, // Zwracamy jako wartość dodatnią dla wygody logowania
            'isDominant' => $isDominant
        ];
    }

    /**
     * Zwraca przewidywany wynik procentowy (0.0 – 1.0) dla gracza przeciwko przeciwnikowi.
     * Przydatne, jeśli chcesz na scoreboardzie wyświetlić informację "Faworyt" lub "Underdog".
     */
    public static function expectedScore(int $playerElo, int $opponentElo): float {
        return 1.0 / (1.0 + pow(10, ($opponentElo - $playerElo) / 400));
    }

    /**
     * Szybki podgląd przed walką (np. do komend lub wyświetlenia potencjalnej nagrody).
     *
     * @return array{potentialGain: int, potentialLoss: int}
     */
    public static function previewDelta(PlayerProfile $player, PlayerProfile $opponent): array {
        // Symulacja wygranej
        $winSimulation = self::calculate($player, $opponent, 300);
        // Symulacja przegranej (odwracamy rolami)
        $lossSimulation = self::calculate($opponent, $player, 300);

        return [
            'potentialGain' => $winSimulation['winnerGain'],
            'potentialLoss' => $lossSimulation['loserLoss']
        ];
    }

    /**
     * Ustala K-Factor na podstawie stażu gracza oraz aktualnych punktów rankingowych.
     */
    private static function kFactor(int $elo, int $matchesPlayed): int {
        if ($matchesPlayed < self::NEW_PLAYER_THRESHOLD) {
            return self::K_NEW_PLAYER;
        }
        if ($elo >= self::HIGH_ELO_THRESHOLD) {
            return self::K_HIGH_ELO;
        }
        return self::K_STANDARD;
    }

    /**
     * Chroni system przed zbyt drastycznymi skokami rankingu.
     */
    private static function clampDelta(int $delta): int {
        return min(self::MAX_ELO_DELTA, abs($delta));
    }
}