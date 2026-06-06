<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Application\Matchmaking\QueueManager;
use Application\Player\SessionManager;
use GameMode\GameModeRegistry;

/**
 * TODO: Komenda /queue <tryb> do dołączania i opuszczania kolejki rankingowej.
 * Sprawdza czy gracz jest w LOBBY, czy tryb istnieje i czy nie jest już w kolejce.
 * Bez argumentu pokazuje listę dostępnych trybów z liczbą graczy w kolejce.
 */
final class QueueCommand extends Command {

    public function __construct(
        private readonly QueueManager     $queueManager,
        private readonly SessionManager   $sessionManager,
        private readonly GameModeRegistry $gameModeRegistry
    ) {
        parent::__construct(
            name:        'queue',
            description: 'Dołącz lub opuść kolejkę matchmakingu.',
            usageMessage: '/queue <tryb> | /queue leave',
            aliases:     ['q', 'arena']
        );
        $this->setPermission('rivarly.play');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('§cTa komenda może być użyta tylko w grze.');
            return;
        }

        // Pobieramy profil gracza używając dedykowanej metody z SessionManager
        $profile = $this->sessionManager->getSession($sender);

        if ($profile === null) {
            $sender->sendMessage('§cTwój profil nie został jeszcze załadowany. Spróbuj ponownie za chwilę.');
            return;
        }

        $uuid = $sender->getUniqueId()->toString();

        // Opuszczanie kolejki: /queue leave
        if (isset($args[0]) && strtolower($args[0]) === 'leave') {
            if (!$this->queueManager->isInQueue($uuid)) {
                $sender->sendMessage('§cNie jesteś w żadnej kolejce.');
                return;
            }

            // Aby poprawnie wywołać dequeue(), musimy znać tryb gry z wpisu w kolejce
            $entry = $this->queueManager->getQueueEntry($uuid);
            if ($entry !== null) {
                $this->queueManager->dequeue($uuid, $entry->gameMode);
            }

            $sender->sendMessage('§aOpuściłeś kolejkę matchmakingu.');
            return;
        }

        // Brak argumentów: /queue -> Wyświetlenie listy trybów wraz z liczbą graczy w kolejce (Wymóg z TODO)
        if (empty($args[0])) {
            $sender->sendMessage('§7=== §9Zarejestrowane Tryby Gry §7===');

            $modes = $this->gameModeRegistry->getAllModes();
            if (empty($modes)) {
                $sender->sendMessage('§cObecnie brak dostępnych trybów gry.');
            } else {
                foreach ($modes as $lowerName => $gameMode) {
                    $playerCount = $this->queueManager->getQueueSize($lowerName);
                    $sender->sendMessage("§8» §b{$gameMode->getName()} §7(W kolejce: §e{$playerCount}§7)");
                }
            }

            $sender->sendMessage('§7Użycie: §f/queue <nazwa_trybu> §7lub §f/queue leave');
            return;
        }

        $modeName = strtolower($args[0]);

        // Walidacja 1: Czy tryb istnieje w rejestrze przy użyciu poprawnej metody exists()
        if (!$this->gameModeRegistry->exists($modeName)) {
            $sender->sendMessage("§cPodany tryb gry '§f{$args[0]}§c' nie istnieje.");
            return;
        }

        // Walidacja 2: Czy gracz nie jest już w kolejce przy użyciu QueueManager
        if ($this->queueManager->isInQueue($uuid)) {
            $sender->sendMessage('§cJesteś już w kolejce! Wpisz §f/queue leave§c, aby ją opuścić.');
            return;
        }

        // Walidacja 3: Czy gracz jest w LOBBY (zgodnie z instrukcją NoMercy)
        if (!$profile->isInLobby()) {
            $sender->sendMessage('§cMusisz być w lobby, aby móc dołączyć do kolejki.');
            return;
        }

        // Dodanie gracza do kolejki przekazując cały obiekt profilu zgodnie z podpisem metody enqueue()
        $success = $this->queueManager->enqueue($profile, $modeName);

        if (!$success) {
            $sender->sendMessage('§cWystąpił nieoczekiwany problem podczas dołączania do kolejki.');
            return;
        }

        $gameModeInstance = $this->gameModeRegistry->getMode($modeName);
        $displayName = $gameModeInstance !== null ? $gameModeInstance->getName() : $modeName;

        $sender->sendMessage("§aDołączyłeś do kolejki rankingowej trybu §e{$displayName}§a. Szukanie przeciwnika...");
    }
}