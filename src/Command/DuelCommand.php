<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use Application\Player\SessionManager;
use Duel\DuelFailReason;
use Duel\DuelManager;
use GameMode\GameModeRegistry;

/**
 * TODO: Komenda /duel <gracz> [tryb] do wysyłania prywatnych zaproszeń do walki.
 * Przekazuje request do DuelManager który zarządza timeoutem i akceptacją.
 * Bez argumentu pokazuje listę aktywnych zaproszeń skierowanych do gracza.
 */
final class DuelCommand extends Command {

    public function __construct(
        private readonly DuelManager      $duelManager,
        private readonly SessionManager   $sessionManager,
        private readonly GameModeRegistry $gameModeRegistry
    ) {
        parent::__construct(
            name:         'duel',
            description:  'Rzuć wyzwanie innemu graczowi na prywatny pojedynek.',
            usageMessage: '/duel <gracz> <tryb> | /duel accept|deny <gracz>',
            aliases:      ['pojedynkuj', 'd']
        );
        $this->setPermission('rivarly.play');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('§cTa komenda może być użyta tylko w grze.');
            return;
        }

        $senderProfile = $this->sessionManager->getSession($sender);

        if ($senderProfile === null) {
            $sender->sendMessage('§cTwój profil nie został jeszcze załadowany.');
            return;
        }

        // Bez argumentów (/duel) -> Pokazuje listę aktywnych zaproszeń
        if (empty($args[0])) {
            $this->handleList($sender);
            return;
        }

        $sub = strtolower($args[0]);

        match ($sub) {
            'accept' => $this->handleAccept($sender, $args),
            'deny', 'decline' => $this->handleDeny($sender, $args),
            default  => $this->handleSend($sender, $senderProfile, $args),
        };
    }

    private function handleSend(Player $sender, $senderProfile, array $args): void {
        if (count($args) < 2) {
            $sender->sendMessage('§7Użycie: §f/duel <gracz> <tryb>');
            return;
        }

        $targetName = $args[0];
        $modeName   = strtolower($args[1]);

        $target = $sender->getServer()->getPlayerExact($targetName);

        if ($target === null || $target === $sender) {
            $sender->sendMessage("§cGracz '§f{$targetName}§c' jest niedostępny lub offline.");
            return;
        }

        $receiverProfile = $this->sessionManager->getSession($target);

        if ($receiverProfile === null) {
            $sender->sendMessage('§cNie można załadować profilu wybranego gracza.');
            return;
        }

        if (!$this->gameModeRegistry->exists($modeName)) {
            $sender->sendMessage("§cPodany tryb gry '§f{$args[1]}§c' nie istnieje.");
            return;
        }

        $result = $this->duelManager->sendRequest($senderProfile, $receiverProfile, $modeName);

        if (!$result->isSuccess()) {
            $sender->sendMessage('§c' . $this->describeFailure($result->getReason()));
            return;
        }

        $gameModeInstance = $this->gameModeRegistry->getMode($modeName);
        $displayName = $gameModeInstance !== null ? $gameModeInstance->getName() : $modeName;

        $sender->sendMessage("§aZaproszenie do walki wysłane do §e{$target->getName()} §a[§f{$displayName}§a]. Wygasa za §f30s§a.");
        $target->sendMessage("§e{$sender->getName()} §awyzywa Cię na pojedynek §e{$displayName}§a! Wpisz §f/duel accept {$sender->getName()}§a, aby zaakceptować.");
    }

    private function handleAccept(Player $sender, array $args): void {
        $receiverProfile = $this->sessionManager->getSession($sender);
        if ($receiverProfile === null) return;

        $senderName = $args[1] ?? null;

        if ($senderName === null) {
            $incoming = $this->duelManager->getPendingRequestsFor($receiverProfile->getUuid());
            if (count($incoming) === 1) {
                $request = reset($incoming);
                $senderName = $request->getSenderName();
            } else {
                $sender->sendMessage('§7Użycie: §f/duel accept <gracz>');
                return;
            }
        }

        // Poprawka deprecation
        $senderPlayer = $sender->getServer()->getPlayerExact((string)$senderName);

        if ($senderPlayer === null) {
            $sender->sendMessage("§cGracz §f{$senderName} §cnie jest już online.");
            return;
        }

        $senderProfile = $this->sessionManager->getSession($senderPlayer);

        if ($senderProfile === null) {
            $sender->sendMessage('§cNie udało się załadować profilu przeciwnika.');
            return;
        }

        $result = $this->duelManager->acceptRequest($receiverProfile, $senderProfile);

        // Poprawka 1000020391.heic - Wywołanie metod gettera z DuelResult
        if (!$result->isSuccess()) {
            $sender->sendMessage('§c' . $this->describeFailure($result->getFailReason()));
            return;
        }

        $sender->sendMessage('§aPojedynek zaakceptowany! Teleportacja...');
        $senderPlayer->sendMessage("§e{$sender->getName()} §azaakceptował Twój pojedynek! Teleportacja...");
    }

    private function handleDeny(Player $sender, array $args): void {
        $receiverProfile = $this->sessionManager->getSession($sender);
        if ($receiverProfile === null) return;

        $senderName = $args[1] ?? null;

        if ($senderName === null) {
            $sender->sendMessage('§7Użycie: §f/duel deny <gracz>');
            return;
        }

        $senderPlayer = $sender->getServer()->getPlayerExact($senderName);

        if ($senderPlayer === null) {
            $sender->sendMessage("§cGracz §f{$senderName} §cjest offline.");
            return;
        }

        $senderProfile = $this->sessionManager->getSession($senderPlayer);

        if ($senderProfile === null) {
            $sender->sendMessage('§cNie udało się załadować profilu przeciwnika.');
            return;
        }

        $result = $this->duelManager->declineRequest($receiverProfile, $senderProfile);

        if (!$result->isSuccess()) {
            $sender->sendMessage('§cBrak oczekujących wyzwań od tego gracza.');
            return;
        }

        $sender->sendMessage("§aOdrzuciłeś wyzwanie od §e{$senderPlayer->getName()}§a.");
        $senderPlayer->sendMessage("§e{$sender->getName()} §codrzucił Twoje zaproszenie do walki.");
    }

    private function handleList(Player $sender): void {
        $profile = $this->sessionManager->getSession($sender);
        if ($profile === null) return;

        // Poprawka 1000020393.heic - Zmiana na getRequests() oraz czyste metody obiektów DTO NoMercy
        $incoming = $this->duelManager->getPendingRequestsFor($profile->getUuid());

        if (empty($incoming)) {
            $sender->sendMessage('§7Nie masz obecnie żadnych oczekujących zaproszeń do walki.');
            return;
        }

        $sender->sendMessage('§7=== §9Oczekujące Wyzwania §7===');
        foreach ($incoming as $request) {
            $sName = $request->getSenderName();
            $gMode = $request->getGameMode();

            // Pobieramy czas; jeśli obiekt nie ma bezpośredniej metody, NoMercy zazwyczaj zwraca domyślną wartość trwania sesji zaproszenia
            $seconds = method_exists($request, 'getRemainingTime') ? $request->getRemainingTime() : 30;

            $sender->sendMessage("§8» §e{$sName} §7[§b{$gMode}§7] — wygasa za: §f{$seconds}s");
        }
    }

    private function describeFailure(?DuelFailReason $reason): string {
        return match ($reason) {
            DuelFailReason::UNKNOWN_GAME_MODE      => 'Nieznany tryb gry.',
            DuelFailReason::SENDER_NOT_IN_LOBBY    => 'Musisz być w lobby, aby wyzywać graczy.',
            DuelFailReason::RECEIVER_NOT_IN_LOBBY  => 'Ten gracz jest aktualnie w walce lub w kolejce.',
            DuelFailReason::RECEIVER_NOT_ACCEPTING => 'Ten gracz nie akceptuje obecnie zaproszeń do walki.',
            DuelFailReason::ALREADY_PENDING        => 'Wysłałeś już zaproszenie do tego gracza. Poczekaj aż wygaśnie.',
            DuelFailReason::REQUEST_NOT_FOUND      => 'Nie znaleziono takiego zaproszenia.',
            DuelFailReason::REQUEST_EXPIRED        => 'To zaproszenie już wygasło.',
            default                                => 'Coś poszło nie tak podczas przetwarzania zaproszenia.',
        };
    }
}