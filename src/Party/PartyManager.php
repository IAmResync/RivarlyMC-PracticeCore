<?php

declare(strict_types=1);

namespace Party;

use pocketmine\player\Player;

/**
 * TODO: Zarządza tworzeniem, rozwiązywaniem i zaproszeniami do grup party.
 * Kontroluje kto jest liderem, pozwala na kick/promote i transfer lidera.
 * Czyści party automatycznie gdy wszyscy gracze wyjdą z serwera.
 */
class PartyManager {

    /** @var array<string, Party> Aktywe grupy: [party_id => Party] */
    private array $parties = [];

    /** @var array<string, string> Mapowanie gracza do ID grupy: [player_uuid => party_id] */
    private array $playerPartyMap = [];

    /**
     * Tworzy nową grupę party z podanym graczem jako liderem.
     */
    public function createParty(Player $leader): Party {
        // Jeśli gracz jest już w jakimś party, najpierw go z niego usuwamy
        if ($this->isInParty($leader)) {
            $this->leaveParty($leader);
        }

        $party = new Party($leader);
        $this->parties[$party->getId()] = $party;
        $this->playerPartyMap[$leader->getUniqueId()->toString()] = $party->getId();

        return $party;
    }

    /**
     * Pobiera obiekt Party, w którym znajduje się dany gracz.
     */
    public function getParty(Player $player): ?Party {
        $partyId = $this->playerPartyMap[$player->getUniqueId()->toString()] ?? null;
        if ($partyId === null) {
            return null;
        }
        return $this->parties[$partyId] ?? null;
    }

    /**
     * Sprawdza, czy gracz jest przypisany do jakiejkolwiek grupy.
     */
    public function isInParty(Player $player): bool {
        return isset($this->playerPartyMap[$player->getUniqueId()->toString()]);
    }

    /**
     * Obsługuje zaproszenie gracza przez lidera grupy.
     */
    public function invitePlayer(Player $leader, Player $target): bool {
        $party = $this->getParty($leader);

        // Tylko lider może zapraszać i party nie może być pełne
        if ($party === null || $party->getLeaderUuid() !== $leader->getUniqueId()->toString() || $party->isFull()) {
            return false;
        }

        $party->invitePlayer($target);
        return true;
    }

    /**
     * Gracz akceptuje zaproszenie i dołącza do party
     */
    public function acceptInvite(Player $player, Party $party): bool {

        if (!$party->hasValidInvite($player) || $party->isFull()) {
            return false;
        }

        // Usuwamy z poprzedniego party, jeśli w jakimś był
        if ($this->isInParty($player)) {
            $this->leaveParty($player);
        }

        if ($party->addMember($player)) {
            $this->playerPartyMap[$player->getUniqueId()->toString()] = $party->getId();
            return true;
        }

        return false;
    }

    /**
     * Gracz dobrowolnie opuszcza swoją aktualną grupę.
     */
    public function leaveParty(Player $player): void {
        $party = $this->getParty($player);

        if ($party === null) {
            return;
        }

        $uuid = $player->getUniqueId()->toString();
        unset($this->playerPartyMap[$uuid]);

        // Jeśli removeMember zwróci false, oznacza to, że party jest puste i należy je rozwiązać
        if (!$party->removeMember($player)) {
            unset($this->parties[$party->getId()]);
        }
    }

    /**
     * Wyrzuca członka z grupy (dostępne tylko dla lidera).
     */
    public function kickMember(Player $leader, Player $target): bool {
        $party = $this->getParty($leader);

        if ($party === null || $party->getLeaderUuid() !== $leader->getUniqueId()->toString()) {
            return false;
        }

        if (!$party->hasMember($target) || $target->getUniqueId()->toString() === $party->getLeaderUuid()) {
            return false; // Nie można wyrzucić samego siebie ani kogoś spoza party
        }

        $targetUuid = $target->getUniqueId()->toString();
        unset($this->playerPartyMap[$targetUuid]);
        $party->removeMember($target);

        return true;
    }

    /**
     * Przekazuje lidera grupy innemu członkowi (Transfer).
     */
    public function transferLeadership(Player $currentLeader, Player $newLeader): bool {
        $party = $this->getParty($currentLeader);

        if ($party === null || $party->getLeaderUuid() !== $currentLeader->getUniqueId()->toString()) {
            return false;
        }

        if (!$party->hasMember($newLeader)) {
            return false;
        }

        // Aby zamienić lidera w encji Party, musimy zasymulować wyjście i powrót starego lidera,
        // lub z poziomu managera odtworzyć strukturę. Najbezpieczniej dla logiki biznesowej:
        $party->addMember($currentLeader);

        // Wymuszamy bezpośrednie ustawienie, jeśli system dobrał kogoś innego niż $newLeader.
        if ($party->getLeaderUuid() !== $newLeader->getUniqueId()->toString()) {
            $party->removeMember($newLeader);
            // Wyczyszczenie i przypisanie ról wymusi ustawienie $newLeader na szczycie tablicy.
            $this->leaveParty($currentLeader);
            $this->createParty($newLeader);
            $this->acceptInvite($currentLeader, $this->getParty($newLeader));
        }

        return true;
    }

    /**
     * Rozwiązuje całą grupę party (np. komenda /party disband).
     */
    public function disbandParty(Party $party): void {
        foreach ($party->getMembers() as $uuid => $name) {
            unset($this->playerPartyMap[$uuid]);
        }
        unset($this->parties[$party->getId()]);
    }

    /**
     * Automatycznie czyści pamięć i usuwa gracza z party, gdy ten wychodzi z serwera.
     */
    public function handlePlayerQuit(Player $player): void {
        $this->leaveParty($player);
    }
}
