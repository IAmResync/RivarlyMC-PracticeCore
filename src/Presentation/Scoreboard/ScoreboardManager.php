<?php

declare(strict_types=1);

namespace Presentation\Scoreboard;

use pocketmine\player\Player;
use Domain\Player\PlayerProfile;

/**
 * TODO: Tworzy, aktualizuje i usuwa scoreboardy dla graczy w lobby i w meczu.
 * Przełącza między widokiem lobby (ELO, dywizja, W/L) a widokiem meczu (HP, czas, kills).
 * Aktualizuje scoreboard gracza przy każdej zmianie stanu (co tick meczu lub na event).
 */
class ScoreboardManager {

    private ScoreboardRenderer $renderer;

    /**
     * Przechowuje timestamp rozpoczęcia meczu dla każdego gracza: [player_uuid => start_timestamp]
     * @var array<string, int>
     */
    private array $matchStartTimes = [];

    /**
     * Cache profili, aby nie obciążać zapytań (w rzeczywistym kodzie podpinam swój SessionManager).
     * @var array<string, PlayerProfile>
     */
    private array $profileCache = [];

    /**
     * Przechowuje aktualny typ widoku dla każdego gracza: [player_uuid => "lobby"/"match"]
     * @var array<string, string>
     */
    private array $playerViews = [];

    public function __construct(ScoreboardRenderer $renderer) {
        $this->renderer = $renderer;
    }

    /**
     * Rejestruje profil gracza w menadżerze (wywoływane np. przy JoinEvent).
     */
    public function registerPlayerProfile(Player $player, PlayerProfile $profile): void {
        $this->profileCache[$player->getUniqueId()->toString()] = $profile;
    }

    /**
     * Inicjalizuje bazowy obiekt scoreboardu dla gracza wchodzącego na serwer.
     */
    public function createScoreboard(Player $player): void {
        $this->renderer->initScoreboard($player, "RivarlyMC.EU");
        // Domyślnie po wejściu gracz trafia do lobby
        $this->setView($player, "lobby");
    }

    /**
     * Przełącza widok scoreboard gracza (np. przy starcie meczu lub powrocie do lobby).
     */
    public function setView(Player $player, string $viewType): void {
        $uuid = $player->getUniqueId()->toString();
        $viewTypeLower = strtolower($viewType);
        $this->playerViews[$uuid] = strtolower($viewTypeLower);

        if ($viewTypeLower === "match") {
            $this->matchStartTimes[$uuid] = time();
        }

        $this->updateScoreboard($player);
    }

    /**
     * Główna metoda aktualizująca zawartość linii tekstowych na podstawie obecnego stanu gracza.
     */
    public function updateScoreboard(Player $player, string $opponentName = "None", int $oponnentHp = 100, int $kills = 0): void {
        $uuid = $player->getUniqueId()->toString();
        $view = $this->playerViews[$uuid] ?? "lobby";

        $lines = [];

        if ($view === "match") {
            $startTime = $this->matchStartTimes[$uuid] ?? time();
            $durationSecounds = time() - $startTime;
            $matchTimeFormatted = sprintf("%02d:%02d", $durationSecounds / 60, $durationSecounds % 60);

            // Statystyki pobierane w czasie meczu (HP przeciwnika , czas meczu, zabójstwa).
            $lines[] = "--------------------";
            $lines[] = "§fTime: §9" . $matchTimeFormatted;
            $lines[] = "§fOpponent: §9" . $opponentName;
            $lines[] = "§fOpponent HP: §9" . $oponnentHp . "%";
            $lines[] = "§fKills: §9" . $kills;
        } else {
            $profile = $this->profileCache[$uuid] ?? null;

            $elo = $profile !== null ? $profile->getGlobalElo() : 1000;
            $globalkills = $profile !== null ? $profile->getGlobalKills() : 0;
            // Sprawdzamy bezpiecznie dywizję. Jeśli obiekt DIvision ma metodę getName(), wywołujemy ją
            $division = ($profile !== null ? $profile->getDivision()->getColor() . $profile->getDivision()->getDisplayName() : "Unranked");
            $winRate = $profile !== null ? $profile->getWinRate() : 0.0;



            // Domyślne statystyki wyświetlane w lobby głównym serwera
            $lines[] = "--------------------";
            $lines[] = "§fGlobal ELO: §9" . $elo;
            $lines[] = "§fDivision: §9" . $division;
            $lines[] = "§fWin Rate: §9" . $winRate . "%";
            $lines[] = "§fGlobal Kills: §9" . $globalkills;
            $lines[] = "";
            $lines[] = "§fOnline: §9" . count($player->getServer()->getOnlinePlayers());
        }

        // Przekazujemy gotowe linie tekstu bezpośrednio do wysyłania przez renderer.
        $this->renderer->renderLines($player, $lines);
    }

    /**
     * Całkowicie usuwa scoreboard z ekranu gracza (np. przy wyjściu z serwera).
     */
    public function removeScoreboard(Player $player): void {
        $uuid = $player->getUniqueId()->toString();
        unset($this->playerViews[$uuid]);

        $this->renderer->removeScoreboard($player);
    }
}
