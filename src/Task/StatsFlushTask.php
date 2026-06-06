<?php

declare(strict_types=1);

namespace Task;

use pocketmine\Server;
use pocketmine\scheduler\Task;
use Application\Player\SessionManager;
use Infrastructure\Database\PlayerRepository;

/**
 * Periodycznie zapisuje statystyki graczy do bazy danych (co 30s / 600 ticks).
 */
class StatsFlushTask extends Task {

    private int $flushCounter = 0;
    private const FLUSHES_BEFORE_LOG = 2;

    public function __construct(
        private readonly SessionManager   $sessionManager,
        private readonly PlayerRepository $playerRepository,
    ) {}

    public function onRun(): void {
        $this->flushCounter++;

        $server = Server::getInstance();
        $onlinePlayers = $server->getOnlinePlayers();

        if (empty($onlinePlayers)) {
            return;
        }

        $savedCount = 0;

        foreach ($onlinePlayers as $player) {
            $uuid    = $player->getUniqueId()->toString();
            $profile = $this->sessionManager->getSessionByUuid($uuid);

            if ($profile !== null) {
                $this->playerRepository->saveProfile(
                    $profile->getUuid(),
                    $profile->getXuid(),
                    $profile->getName(),
                    $profile->getGlobalElo(),
                    $profile->getGlobalKills(),
                    $profile->getGlobalDeaths(),
                );
                $savedCount++;
            }
        }

        if ($this->flushCounter % self::FLUSHES_BEFORE_LOG === 0) {
            $server->getLogger()->debug("[StatsFlusher] Saved $savedCount player profile(s) to database.");
        }
    }
}
