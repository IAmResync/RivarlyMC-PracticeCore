<?php

declare(strict_types=1);

namespace Presentation\Scoreboard;

use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;

/**
 * TODO: Buduje listę linii tekstu wyświetlanych na scoreboardzie gracza.
 * Dla lobby: tytuł serwera, ELO, dywizja, W/L, pasek ELO, event/turniej info.
 * Dla meczu: przeciwnik, HP, czas, kills, CPS, ping (używa FormatterUtil).
 */
class ScoreboardRenderer {

    private const OBJECTIVE_NAME = "rivarly_sb";

    /**
     * Inicjalizuje podstawowy slot scoreboardu (sidebar) po prawej stronie ekranu.
     */
    public function initScoreboard(Player $player, string $title): void {
        $pk = new SetDisplayObjectivePacket();
        $pk->displaySlot = "sidebar";
        $pk->objectiveName = self::OBJECTIVE_NAME;
        $pk->displayName = $title;
        $pk->criteriaName = "dummy";
        $pk->sortOrder = 0;

        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /**
     * Czyści stare wpisy i wysyła zupełnie nowe linie tekstu do klienta gry.
     */
    public function renderLines(Player $player, array $lines): void {
        // Najpierw czyścimy stare linie, wysyłając pakiet czyszczący (REMOVE).
        $this->clearLines($player);

        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_CHANGE;

        // Idziemy od tyłu, aby linie układały się od góry do dołu na sidebarze
        $score = 1;
        foreach (array_reverse($lines) as $line) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = self::OBJECTIVE_NAME;
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
            $entry->customName = $line;
            $entry->score = $score;
            $entry->scoreboardId = $score;

            $pk->entries[] = $entry;
            $score++;
        }

        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /**
     * Całkowicie usuwa scoreboard z ekranu gracza.
     */
    public function removeScoreboard(Player $player): void {
        $pk = new SetDisplayObjectivePacket();
        $pk->displaySlot = "sidebar";
        $pk->objectiveName = self::OBJECTIVE_NAME;
        $pk->displayName = "";
        $pk->criteriaName = "dummy";
        $pk->sortOrder = 0;

        $player->getNetworkSession()->sendDataPacket($pk);
    }

    /**
     * Wewnętrzna metoda usuwająca wpisy z aktualnego widoku (niezbędna przed nadpisaniem).
     */
    private function clearLines(Player $player): void {
        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_REMOVE;

        // Usuwamy maksymalnie do 15 potencjalnych linii.
        for ($i = 1; $i <= 15; $i++) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = self::OBJECTIVE_NAME;
            $entry->scoreboardId = $i;
            $entry->score = $i;
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;

            $pk->entries[] = $entry;
        }

        $player->getNetworkSession()->sendDataPacket($pk);
    }
}
