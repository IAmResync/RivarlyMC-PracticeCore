<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Application\Player\SessionManager;
use Duel\DuelManager;
use Spectator\SpectatorManager;

/**
 * TODO: Komenda /spectate <gracz> do obserwowania aktywnego meczu innego gracza.
 * Przekazuje żądanie do SpectatorManager który teleportuje i ustawia tryb widza.
 * Bez argumentu pokazuje listę aktualnie trwających meczów możliwych do obserwacji.
 */
final class SpectateCommand extends Command {

    public function __construct(
        private readonly SpectatorManager $spectatorManager,
        private readonly SessionManager   $sessionManager,
        private readonly DuelManager      $duelManager // Potrzebny do pobrania areny i ID meczu celu
    ) {
        parent::__construct(
            name:         'spectate',
            description:  'Obserwuj trwające pojedynki graczy.',
            usageMessage: '/spectate [gracz] | /spectate leave',
            aliases:      ['obserwuj', 'spec']
        );
        $this->setPermission('rivarly.play');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('§cTa komenda może być użyta tylko w grze.');
            return;
        }

        $profile = $this->sessionManager->getSession($sender);
        if ($profile === null) {
            $sender->sendMessage('§cTwój profil nie został jeszcze załadowany.');
            return;
        }

        // Bez argumentu pokazuje listę aktualnie trwających meczów
        if (empty($args[0])) {
            $this->handleList($sender);
            return;
        }

        // Obsługa wyjścia z trybu widza
        if (strtolower($args[0]) === 'leave') {
            if ($this->spectatorManager->isSpectator($sender)) {
                $this->spectatorManager->removeSpectator($sender);
                // Tutaj gracz wraca na spawn lub do lobby
                $sender->teleport($sender->getServer()->getWorldManager()->getDefaultWorld()?->getSpawnLocation());
                $sender->sendMessage('§aOpuściłeś tryb obserwatora i wróciłeś do lobby.');
            } else {
                $sender->sendMessage('§cNie jesteś obecnie obserwatorem żadnego meczu.');
            }
            return;
        }

        $targetName = $args[0];
        $target = $sender->getServer()->getPlayerExact($targetName);

        if ($target === null) {
            $sender->sendMessage("§cGracz '§f{$targetName}§c' jest offline.");
            return;
        }

        if ($target === $sender) {
            $sender->sendMessage('§cNie możeszfilmować lub obserwować samego siebie!');
            return;
        }

        // Szukamy aktywnej walki wybranego gracza w DuelManager
        // Metoda i jej DTO dostosowane do standardu NoMercy (szukanie aktywnej sesji meczu)
        $match = method_exists($this->duelManager, 'getMatchByPlayer')
            ? $this->duelManager->getMatchByPlayer($target)
            : (method_exists($this->duelManager, 'getActiveMatch') ? $this->duelManager->getActiveMatch($target) : null);

        if ($match === null) {
            $sender->sendMessage("§cGracz §f{$target->getName()} §cnie bierze obecnie udziału w żadnym aktywnym meczu.");
            return;
        }

        // Wyciągamy ID pojedynku oraz lokację areny z obiektu meczu
        $matchId = method_exists($match, 'getId') ? $match->getId() : ($match->id ?? null);
        $arenaSpawn = method_exists($match, 'getArenaSpawn') ? $match->getArenaSpawn() : ($match->arenaSpawn ?? $target->getPosition());

        if ($matchId === null) {
            $sender->sendMessage('§cNie udało się zidentyfikować ID trwającego pojedynku.');
            return;
        }

        // Poprawka 1000020395.heic - Wywołanie prawidłowej metody z Twojego SpectatorManagera
        $this->spectatorManager->addSpectator($sender, (string)$matchId, $arenaSpawn);
        $sender->sendMessage("§aRozpocząłeś obserwowanie walki gracza §e{$target->getName()}§a!");
    }

    private function handleList(Player $sender): void {
        // Pobieramy trwające mecze z managera pojedynków
        $activeMatches = method_exists($this->duelManager, 'getActiveMatches')
            ? $this->duelManager->getActiveMatches()
            : [];

        if (empty($activeMatches)) {
            $sender->sendMessage('§7Obecnie nie trwają żadne pojedynki, które można obserwować.');
            return;
        }

        $sender->sendMessage('§7=== §eAktywne Mecze do Obserwowania §7===');
        foreach ($activeMatches as $match) {
            $p1 = method_exists($match, 'getPlayer1Name') ? $match->getPlayer1Name() : ($match->p1Name ?? 'Gracz 1');
            $p2 = method_exists($match, 'getPlayer2Name') ? $match->getPlayer2Name() : ($match->p2Name ?? 'Gracz 2');
            $mode = method_exists($match, 'getGameModeName') ? $match->getGameModeName() : ($match->modeName ?? 'Unknown');

            $sender->sendMessage("§8» §f{$p1} §7vs §f{$p2} §7[§b{$mode}§7] — §a/spectate {$p1}");
        }
    }
}