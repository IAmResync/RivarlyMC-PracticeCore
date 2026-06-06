<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use Application\Player\SessionManager;
use Party\PartyManager;
use Party\PartyDuelBridge;

final class PartyCommand extends Command {

    public function __construct(
        private readonly PartyManager $partyManager,
        private readonly PartyDuelBridge $partyDuelBridge,
        private readonly SessionManager $sessionManager
    ) {
        parent::__construct('party', 'Zarządzanie drużyną.', '/party [invite|leave|kick|duel|info]', ['p', 'druzyna']);
        $this->setPermission('rivarly.play');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) return;
        if (empty($args[0])) { $this->sendHelp($sender); return; }

        switch (strtolower($args[0])) {
            case 'invite':
                if (isset($args[1]) && ($target = Server::getInstance()->getPlayerExact($args[1])) !== null) {
                    if (!$this->partyManager->isInParty($sender)) $this->partyManager->createParty($sender);
                    $this->partyManager->invitePlayer($sender, $target)
                        ? $sender->sendMessage("§aZaproszono.")
                        : $sender->sendMessage("§cBłąd.");
                }
                break;

            case 'leave':
                $this->partyManager->leaveParty($sender);
                $sender->sendMessage("§aOpuściłeś party.");
                break;

            case 'kick':
                if (isset($args[1]) && ($target = Server::getInstance()->getPlayerExact($args[1])) !== null) {
                    $this->partyManager->kickMember($sender, $target)
                        ? $sender->sendMessage("§aWyrzucono.")
                        : $sender->sendMessage("§cBłąd.");
                }
                break;

            case 'duel':
            case 'pojedynek':
                // Przykład użycia: /party duel [tryb] [nick_lidera_drugiej_druzyny]
                if (empty($args[1]) || empty($args[2])) {
                    $sender->sendMessage('§cPoprawne użycie: /party duel [tryb] [lider]');
                    return;
                }

                $gameMode = strtolower($args[1]);
                $targetLeaderName = $args[2];

                // 1. Pobieramy Party wysyłającego (używamy getParty, bo tak nazywa się metoda w PartyManager)
                $partyA = $this->partyManager->getParty($sender);
                $targetLeader = Server::getInstance()->getPlayerExact($targetLeaderName);

                if ($partyA === null || $targetLeader === null || ($partyB = $this->partyManager->getParty($targetLeader)) === null) {
                    $sender->sendMessage('§cNie znaleziono drużyn.');
                    return;
                }

                // 2. Mapujemy członków Party na tablice PlayerProfile (dla PartyDuelBridge)
                $partyAMembersProfiles = [];
                foreach ($partyA->getMembers() as $uuid => $name) {
                    $player = Server::getInstance()->getPlayerExact($name);
                    if ($player !== null && ($profile = $this->sessionManager->getSession($player)) !== null) {
                        $partyAMembersProfiles[$uuid] = $profile;
                    }
                }

                $partyBMembersProfiles = [];
                foreach ($partyB->getMembers() as $uuid => $name) {
                    $player = Server::getInstance()->getPlayerExact($name);
                    if ($player !== null && ($profile = $this->sessionManager->getSession($player)) !== null) {
                        $partyBMembersProfiles[$uuid] = $profile;
                    }
                }

                // 3. Wywołujemy logikę z PartyDuelBridge
                $result = $this->partyDuelBridge->startPartyDuel($partyAMembersProfiles, $partyBMembersProfiles, $gameMode);

                if ($result->success) {
                    $sender->sendMessage('§aPojedynek wystartował! MatchID: ' . $result->matchId);
                } else {
                    $sender->sendMessage('§cBłąd: ' . $result->reason->name);
                }
                break;

            case 'info':
                $this->displayPartyInfo($sender);
                break;
        }
    }

    private function displayPartyInfo(Player $player): void {
        $party = $this->partyManager->getParty($player);
        if ($party === null) {
            $player->sendMessage('§cNie jesteś w drużynie.');
            return;
        }

        $player->sendMessage('§7--- §b§lDRUŻYNA §7---');
        foreach ($party->getMembers() as $uuid => $name) {
            // Poprawka: Pobranie serwera przez gracza
            $onlinePlayer = $player->getServer()->getPlayerExact($name);
            $status = '§aLobby';
            if ($onlinePlayer !== null) {
                $profile = $this->sessionManager->getSession($onlinePlayer);
                if ($profile !== null && method_exists($profile, 'isInMatch') && $profile->isInMatch()) {
                    $status = '§cW meczu ⚔️';
                }
            }
            $role = ($uuid === $party->getLeaderUuid()) ? '§6[Lider]' : '§7[Członek]';
            $player->sendMessage("  §8• {$role} §f{$name} §7— Status: {$status}");
        }
    }

    private function sendHelp(Player $player): void {
        $player->sendMessage('§e/party [invite|leave|kick|info]');
    }
}