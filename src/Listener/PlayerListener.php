<?php

declare(strict_types=1);

namespace Listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use Application\Match\MatchManager;
use Application\Matchmaking\QueueManager;
use Application\Player\SessionManager;
use Presentation\HotBar\HotBarManager;
use Presentation\Scoreboard\ScoreboardManager;

/**
 * Handles player lifecycle: join, quit, movement, state changes.
 */
final class PlayerListener implements Listener {

    public function __construct(
        private readonly SessionManager   $sessionManager,
        private readonly QueueManager     $queueManager,
        private readonly MatchManager     $matchManager,
        private readonly HotBarManager    $hotBarManager,
        private readonly ScoreboardManager $scoreboardManager,
    ) {}

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();

        // Ładujemy profil z DB asynchronicznie
        $this->sessionManager->createSession($player);

        // Dajemy itemy lobby
        $this->hotBarManager->sendLobbyHotBar($player);

        // Tworzymy scoreboard (profil może być jeszcze null, updateuje się po załadowaniu)
        $this->scoreboardManager->createScoreboard($player);

        $player->sendMessage("§a✓ §fWelcome to §bRivarlyMC§f!");
        $player->sendMessage("§7Type §f/queue <mode> §7to start a match, or §f/duel §7to challenge a player.");
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $uuid = $player->getUniqueId()->toString();

        $this->scoreboardManager->removeScoreboard($player);
        $this->sessionManager->closeSession($player);
        $this->queueManager->dequeueAll($uuid);

        $match = $this->matchManager->getMatchByPlayer($uuid);
        if ($match !== null && $match->isActive()) {
            $opponentUuid = $match->getOpponentUuid($uuid);
            if ($opponentUuid !== null) {
                $opponentProfile = $this->sessionManager->getSessionByUuid($opponentUuid);
                $playerProfile   = $this->sessionManager->getSessionByUuid($uuid);

                if ($opponentProfile !== null && $playerProfile !== null) {
                    $opponentPlayer = $match->getPlayers()[$opponentUuid] ?? null;
                    if ($opponentPlayer !== null) {
                        $this->matchManager->endMatch(
                            matchId:       $match->getMatchId(),
                            winnerUuid:    $opponentUuid,
                            winnerProfile: $opponentProfile,
                            loserProfile:  $playerProfile,
                        );
                    }
                }
            }
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        // Rozszerzalne w przyszłości
    }
}
