<?php

declare(strict_types=1);

namespace Party;

use pocketmine\player\Player;

/**
 * TODO: Encja grupy graczy z jednym liderem i max N członkami.
 * Przechowuje UUID lidera, listę członków i ewentualne zaproszenia oczekujące.
 * Waliduje limity rozmiaru party (np. max 4 graczy) i blokuje duplikaty.
 */
final class Party {

    private string $id;
    private string $leaderUuid;
    private string $leaderName;

    /** @var array<string, string> Lista członków: [player_uuid => player_name] */
    private array $members = [];

    /** @var array<string, int> Oczekujące zaproszenia: [player_uuid => timestamp_wygaśnięcia] */
    private array $invitedPlayers = [];

    // Limit rozmiaru grupy określony
    private const MAX_MEMBERS = 4;
    private const INVITE_TIMEOUT = 60;

    public function __construct(Player $leader) {
        $this->id = uniqid("party_");
        $this->leaderUuid = $leader->getUniqueId()->toString();
        $this->leaderName = $leader->getName();

        // Lider jako pierwszy członek
        $this->members[$this->leaderUuid] = $this->leaderName;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getLeaderUuid(): string {
        return $this->leaderUuid;
    }

    public function getLeaderName(): string {
        return $this->leaderName;
    }

    /**
     * Zwraca listę członków grupy.
     * @return array<string, string>
     */
    public function getMembers(): array {
        return $this->members;
    }

    public function getMemberCount(): int {
        return count($this->members);
    }

    /**
     * Sprawdza, czy w party jest jeszcze wolne miejsce.
     */
    public function isFull(): bool {
        return count($this->members) >= self::MAX_MEMBERS;
    }

    /**
     * Sprawdza, czy gracz jest już członkiem grupy (zabezpieczenie przed duplikatami).
     */
    public function hasMember(Player $player): bool {
        return isset($this->members[$player->getUniqueId()->toString()]);
    }

    /**
     * Dodaje członka do grupy po przejściu walidacji.
     * @return bool True jeśli dodano pomyślnie, false jeśli w przypadku błędu (brak miejsca/duplikat)
     */
    public function addMember(Player $player): bool {
        // Walidacja: Blokowanie duplikatów
        if ($this->hasMember($player)) {
            return false;
        }

        // Walidacja: Limit rozmiaru party
        if ($this->isFull()) {
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        $this->members[$uuid] = $player->getName();

        // Czyszczenie oczekującego zaproszenia
        unset($this->invitedPlayers[$uuid]);
        return true;
    }

    /**
     * Usuwa gracza z party. Obsługuje też zmianę lidera.
     * @return bool True jeśli party nadal istnieje, false jeśli jest puste.
     */
    public function removeMember(Player $player): bool {
        $uuid = $player->getUniqueId()->toString();

        if (!isset($this->members[$uuid])) {
            return true;
        }
        unset($this->members[$uuid]);

        if (count($this->members) === 0) {
            return false;
        }

        // Zmiana lidera w locie, jeśli stary wyszedł
        if ($uuid === $this->leaderUuid) {
            $newLeaderUuid = (string) array_key_first($this->members);
            $this->leaderUuid = $newLeaderUuid;
            $this->members[$newLeaderUuid] = $player->getServer()->getPlayerByRawUUID($newLeaderUuid)?->getName() ?? "Unknown";
        }

        return true;
    }

    // ------------------------------------------------
   // System Zaproszeń Oczekujących (Invitations)
   // -------------------------------------------------

    public function invitePlayer(Player $player): void {
        $this->invitedPlayers[$player->getUniqueId()->toString()] = time() + self::INVITE_TIMEOUT;
    }

    public function hasValidInvite(Player $player): bool {
        $uuid = $player->getUniqueId()->toString();

        if (!isset($this->invitedPlayers[$uuid])) {
            return false;
        }

        if (time() > $this->invitedPlayers[$uuid]) {
            unset($this->invitedPlayers[$uuid]);
            return false;
        }

        return true;
    }

    public function removeInvite(Player $player): void {
        unset($this->invitedPlayers[$player->getUniqueId()->toString()]);
    }
}
