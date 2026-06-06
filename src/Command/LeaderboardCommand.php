<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use Infrastructure\Cache\LeaderboardCache;

/**
 * TODO: Komenda /leaderboard [tryb] wyświetlająca top 10 graczy w danym trybie.
 * Pobiera dane z LeaderboardCache (Redis) dla minimalnego opóźnienia.
 * Bez argumentu pokazuje globalny ranking ELO ze wszystkich trybów.
 */
final class LeaderboardCommand extends Command {

    public function __construct(
        private readonly LeaderboardCache $leaderboardCache
    ) {
        parent::__construct(
            name:         'leaderboard',
            description:  'Wyświetla top 10 najlepszych graczy.',
            usageMessage: '/leaderboard [tryb]',
            aliases:      ['top', 'topka', 'ranking']
        );
        $this->setPermission('rivarly.play');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        // Ponieważ aktualny LeaderboardCache operuje na jednym globalnym zbiorze Redis,
        // informujemy gracza, jeśli spróbuje wyszukać specyficzny tryb, którego cache jeszcze nie obsługuje.
        if (!empty($args[0]) && strtolower($args[0]) !== 'global') {
            $sender->sendMessage("§7Filtrowanie trybu '§e" . strtolower($args[0]) . "§7' będzie dostępne wkrótce. Wyświetlam ranking globalny:");
        }

        // Wywołanie poprawnej metody getTopPlayers z limitem 10 z Twojej klasy cache
        $topData = $this->leaderboardCache->getTopPlayers(10);

        if (empty($topData)) {
            $sender->sendMessage('§cObecnie brak danych rankingowych w bazie Redis.');
            return;
        }

        $sender->sendMessage('§7----------------------------------------');
        $sender->sendMessage('§🏆 §e§lTOP 10 — GLOBALNY ELO §r');
        $sender->sendMessage('§7----------------------------------------');

        $position = 1;
        // Pętla dostosowana do formatu tablicy asocjacyjnej [nick => punkty_elo]
        foreach ($topData as $name => $elo) {
            // Złoty, srebrny i brązowy kolor dla podium
            $posString = match($position) {
                1 => '§6§l1.§r ',
                2 => '§e§l2.§r ',
                3 => '§f§l3.§r ',
                default => "§7{$position}. "
            };

            // Pierwsza litera nicku z wielkiej litery dla zachowania estetyki na czacie
            $formattedName = ucfirst($name);

            $sender->sendMessage("{$posString}§f{$formattedName} §7— §b{$elo} §aELO");
            $position++;
        }

        $sender->sendMessage('§7----------------------------------------');
    }
}