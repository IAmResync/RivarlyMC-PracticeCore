<?php

declare(strict_types=1);

namespace Application\Season;

use pocketmine\player\Player;

/**
 * TODO: Value object definiujący co dostaje gracz na danej pozycji w rankingu końca sezonu.
 * Określa progi (np. Top 1-10 → cape "Season Champion", Top 11-50 → kill effect "Gold Fire").
 * Używany przez SeasonResetService do automatycznej dystrybucji nagród.
 */
final class SeasonRewardRule {

    /**
     * Automatycznie sprawdza pozycję i przyznaje graczowi nagrody na koniec sezonu.
     */
    public function grandRewardForSeason(Player $player, int $rank): void {
        $name = $player->getName();

        if ($rank >= 1 && $rank <= 10) {
            $player->sendMessage("Gratulacje {$name}! Zająłeś Top {$rank} w tym sezonie!");
            $player->sendMessage("Otrzymujesz ekskluzywną pelerynę: Season Champion!");
            // Tutaj można wpiąć system kosmetyczny dla peleryny!
            return;
        }

        if ($rank >= 11 && $rank <= 50) {
            $player->sendMessage("Gratulacje {$name}! Zająłeś Top {$rank} w tym sezonie!");
            $player->sendMessage("Otrzymujesz efekt po zabójstwie: Gold Fire!");
            // Tutaj można wpiąć system kosmetyczny dla efektów
            return;
        }

        // Nagroda pocieszenia dla reszty graczy biorących udział
        $player->sendMessage("Dziękujemy za udział w sezonie, {$name}! Powodzenia w nowym sezonie!");
        // Tutaj można podarować nagrodę pocieszenia dla graczy którzy brali udział w sezonie
    }
}
