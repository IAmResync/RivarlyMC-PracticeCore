<?php

declare(strict_types=1);

namespace Application\Player;

use Logger;
use pocketmine\player\Player;
use Domain\Player\PlayerProfile;
use Infrastructure\Database\PlayerRepository;

/**
 * Zarządza cyklem życia sesji gracza od wejścia na serwer do wyjścia.
 */
class SessionManager {

    /** @var array<string, PlayerProfile> klucz: UUID gracza */
    private array $sessions = [];

    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly Logger $logger,
    ) {}

    /**
     * Asynchronicznie ładuje statystyki z DB i buduje profil gracza w RAM.
     * Tymczasowy profil z domyślnym ELO jest ustawiany od razu.
     */
    public function createSession(Player $player): void {
        $uuid = $player->getUniqueId()->toString();
        $xuid = $player->getXuid();
        $name = $player->getName();

        // Profil tymczasowy — zastąpiony po odpowiedzi DB
        $this->sessions[$uuid] = new PlayerProfile(
            uuid: $uuid,
            xuid: $xuid,
            name: $name,
            globalElo: 1000,
        );

        $this->playerRepository->loadProfile(
            $name,
            function (array $row) use ($uuid, $xuid, $name): void {
                $profile = new PlayerProfile(
                    uuid: $uuid,
                    xuid: $xuid,
                    name: $name,
                    globalElo: (int) ($row['elo'] ?? 1000),
                );
                $profile->setLoadedStats(
                    kills:  (int) ($row['kills'] ?? 0),
                    deaths: (int) ($row['deaths'] ?? 0),
                );
                $this->sessions[$uuid] = $profile;
            },
            function (\Throwable $throwable) use ($name): void {
                $this->logger->error(
                    "Nie udało się załadować profilu dla gracza {$name}: " . $throwable->getMessage()
                );
            }
        );
    }

    /**
     * Zwraca aktywny profil gracza z RAM.
     */
    public function getSession(Player $player): ?PlayerProfile {
        return $this->sessions[$player->getUniqueId()->toString()] ?? null;
    }

    /**
     * Zwraca profil gracza na podstawie UUID.
     */
    public function getSessionByUuid(string $uuid): ?PlayerProfile {
        return $this->sessions[$uuid] ?? null;
    }

    /**
     * Zapisuje profil do DB i czyści go z RAM przy wyjściu gracza.
     */
    public function closeSession(Player $player): void {
        $uuid = $player->getUniqueId()->toString();

        if (!isset($this->sessions[$uuid])) {
            return;
        }

        $profile = $this->sessions[$uuid];

        $this->playerRepository->saveProfile(
            $profile->getUuid(),
            $profile->getXuid(),
            $profile->getName(),
            $profile->getGlobalElo(),
            $profile->getGlobalKills(),
            $profile->getGlobalDeaths()
        );

        unset($this->sessions[$uuid]);
    }

    /**
     * Zwraca wszystkie aktywne sesje (używane przez StatsFlushTask).
     * @return array<string, PlayerProfile>
     */
    public function getAllSessions(): array {
        return $this->sessions;
    }
}
