<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use Application\Player\SessionManager;
use Rematch\RematchFailReason;
use Rematch\RematchManager;

final class RematchCommand extends Command {

    public function __construct(
        private readonly RematchManager $rematchManager,
        private readonly SessionManager $sessionManager,
    ) {
        parent::__construct(
            name:         'rematch',
            description:  'Accept or decline a rematch offer.',
            usageMessage: '/rematch | /rematch deny',
        );
        $this->setPermission('rivarly.play');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage('§cThis command can only be used in-game.');
            return;
        }

        // Poprawka: używamy getSession($sender) zgodnie z Twoim SessionManager
        $receiverProfile = $this->sessionManager->getSession($sender);

        if ($receiverProfile === null) {
            $sender->sendMessage('§cYour profile is not loaded yet.');
            return;
        }

        $sub = strtolower($args[0] ?? 'accept');

        if ($sub === 'deny') {
            $this->handleDeny($sender, $receiverProfile);
            return;
        }

        $this->handleAccept($sender, $receiverProfile);
    }

    private function handleAccept(Player $sender, $receiverProfile): void {
        // 1. Używamy nowej metody, aby znaleźć rewanż
        $request = $this->rematchManager->getPendingRequestFor($receiverProfile->getUuid());

        if ($request === null) {
            $sender->sendMessage('§cYou have no pending rematch offer.');
            return;
        }

        // 2. Pobieramy przeciwnika (pamiętaj: request zawiera obie strony)
        $opponentUuid = ($request->senderUuid === $receiverProfile->getUuid()) ? $request->receiverUuid : $request->senderUuid;
        $opponentPlayer = Server::getInstance()->getPlayerByUUID(Uuid::fromString($opponentUuid));

        $opponentProfile = $this->sessionManager->getSession($opponentPlayer);

        if ($opponentProfile === null) {
            $sender->sendMessage('§cCould not load opponent profile.');
            return;
        }

        // 3. Akceptujemy rewanż
        $result = $this->rematchManager->acceptRematch($receiverProfile, $opponentProfile);

        // 4. Obsługa wyniku (pamiętaj: w RematchResult właściwości są public readonly!)
        if (!$result->success) {
            $msg = match ($result->reason) {
                RematchFailReason::REQUEST_EXPIRED         => '§cThe rematch offer has expired.',
                RematchFailReason::OPPONENT_NOT_IN_LOBBY   => '§cYour opponent is no longer available.',
                RematchFailReason::REQUESTER_NOT_IN_LOBBY  => '§cYou must be in the lobby to accept a rematch.',
                default                                                     => '§cCould not start rematch.',
            };
            $sender->sendMessage($msg);
            return;
        }

        $sender->sendMessage('§9Rematch accepted! Teleporting...');
    }

    private function handleDeny(Player $sender, $receiverProfile): void {
        $request = $this->rematchManager->getPendingRequestFor($receiverProfile->getUuid());

        if ($request === null) {
            $sender->sendMessage('§cYou have no pending rematch offer.');
            return;
        }

        $winnerUuid = ($request->senderUuid === $receiverProfile->getUuid()) ? $request->receiverUuid : $request->senderUuid;
        $winnerPlayer = Server::getInstance()->getPlayerByUUID(Uuid::fromString($winnerUuid));

        $this->rematchManager->declineRematch(
            $receiverProfile,
            $this->sessionManager->getSession($winnerPlayer) ?? $receiverProfile
        );

        $sender->sendMessage('§9Rematch declined.');

        if ($winnerPlayer !== null) {
            $winnerPlayer->sendMessage("§f{$sender->getName()} §9declined the rematch.");
        }
    }
}