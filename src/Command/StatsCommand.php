<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Application\Player\SessionManager;
use Domain\Player\PlayerProfile;

/**
 * TODO: Komenda /stats [gracz] wyświetlająca profil i statystyki w czacie.
 * Pokazuje: ELO, dywizję, W/L, KDR, win rate, win streak i ostatnie mecze (historia ELO).
 * Bez argumentu wyświetla statystyki wywołującego gracza, z argumentem - innego.
 */
final class StatsCommand extends Command {

    public function __construct(
        private readonly SessionManager $sessionManager
    ) {
        parent::__construct(
            name:         'stats',
            description:  'Wyświetla statystyki profilu gracza.',
            usageMessage: '/stats [gracz]',
            aliases:      ['statystyki', 'profil']
        );
        $this->setPermission('command.default');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (empty($args[0])) {
            if (!$sender instanceof Player) {
                $sender->sendMessage('§cMusisz podać nick gracza, używając tej komendy z poziomu konsoli.');
                return;
            }
            $targetPlayer = $sender;
        } else {
            $targetPlayer = $sender->getServer()->getPlayerExact($args[0]);
        }

        // Obsługa wyświetlania profilu gracza ONLINE
        if ($targetPlayer instanceof Player) {
            $profile = $this->sessionManager->getSession($targetPlayer);

            if ($profile === null) {
                $sender->sendMessage('§cProfil gracza nie został jeszcze w pełni załadowany.');
                return;
            }

            $this->displayStats($sender, $targetPlayer->getName(), $profile);
            return;
        }

        // Obsługa gracza OFFLINE (Jeśli SessionManager posiada cache/metodę pobierania)
        $targetName = $args[0];
        if (method_exists($this->sessionManager, 'getOfflineSession')) {
            $offlineProfile = $this->sessionManager->getOfflineSession($targetName);
            if ($offlineProfile instanceof PlayerProfile) {
                $this->displayStats($sender, $targetName, $offlineProfile);
                return;
            }
        }

        $sender->sendMessage("§cGracz '§f{$targetName}§c' nie został znaleziony lub nigdy nie grał na serwerze.");
    }

    /**
     * Renderuje i wysyła sformatowane statystyki na czat na podstawie encji PlayerProfile.
     */
    private function displayStats(CommandSender $sender, string $name, PlayerProfile $profile): void {
        $elo       = $profile->getGlobalElo();
        $division  = $profile->getDivision();
        $wins      = $profile->getGlobalWins();
        $losses    = $profile->getGlobalLosses();
        $kills     = $profile->getGlobalKills();
        $deaths    = $profile->getGlobalDeaths();
        $winStreak = $profile->getCurrentWinStreak();

        // Wykorzystujemy wbudowane, bezpieczne metody obliczeniowe z encji PlayerProfile
        $kdr     = $profile->getKdr();
        $winRate = $profile->getWinRate();

        $sender->sendMessage('§7----------------------------------------');
        $sender->sendMessage("§9Statystyki gracza: §e{$name}");
        $sender->sendMessage('§7----------------------------------------');
        $sender->sendMessage("§8» §7Ranking (ELO): §a{$elo} §7(§b{$division}§7)");
        $sender->sendMessage("§8» §7Seria zwycięstw (Streak): §e{$winStreak} 🔥");
        $sender->sendMessage("§8» §7Zabójstwa/Śmierci (KDR): §f{$kills}§7/§c{$deaths} §7(§a{$kdr}§7)");
        $sender->sendMessage("§8» §7Wygrane/Przegrane (W/L): §a{$wins}§7/§c{$losses} §7(§e{$winRate}% WR§7)");

        // Sekcja: Ostatnie mecze (Pobierane z historii zmian ELO obiektu PlayerProfile)
        $eloHistory = $profile->getEloHistory();

        // Sekcja: Ostatnie mecze (Pobierane z historii zmian ELO obiektu PlayerProfile)
        $eloHistory = $profile->getEloHistory();

        if (!empty($eloHistory)) {
            $sender->sendMessage('§7Ostatnie pojedynki:');
            // Wyświetlamy max 3 ostatnie wpisy z historii
            foreach (array_slice($eloHistory, 0, 3) as $entry) {
                // Wywołujemy publiczne gettery zamiast prywatnych pól encji EloHistoryEntry
                $opponent = $entry->getOpponentName();
                $won      = $entry->isWon();
                $delta    = $entry->getDelta();

                $resultColor = $won ? '§aWYGRANA' : '§cPRZEGRANA';
                $deltaSign   = $delta >= 0 ? "+{$delta}" : (string)$delta;

                $sender->sendMessage("  §8• {$resultColor} §7vs §f{$opponent} §7(§e{$deltaSign} ELO§7)");
            }
        } else {
            $sender->sendMessage('§8» §7Ostatnie mecze: §fBrak rozegranych gier rankingowych.');
        }
        $sender->sendMessage('§7----------------------------------------');
    }
}