<?php

declare(strict_types=1);

namespace Presentation\Nametag;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use Application\Player\SessionManager;

/**
 * Repeating task that refreshes nametags for all online players every 10 seconds.
 *
 * Why a task and not an event?
 *   - Division can change mid-session (ELO goes up/down after a match)
 *   - PMMP doesn't fire an event for nametag changes — we need to push updates
 *   - 10-second interval is fast enough to feel live, cheap enough to not lag
 *
 * Registration in Plugin::onEnable():
 *   $plugin->getScheduler()->scheduleRepeatingTask(
 *       new NametagTask($sessionManager, $renderer),
 *       200 // 10 seconds = 200 ticks
 *   );
 *
 * The task calls Player::setNameTag() for every online player whose profile
 * is loaded — players still loading (isLoading) are skipped safely.
 */
final class NametagTask extends Task {

    public function __construct(
        private readonly SessionManager  $sessionManager,
        private readonly NametagRenderer $renderer,
    ) {}

    public function onRun(): void {
        $server = Server::getInstance();

        foreach ($server->getOnlinePlayers() as $player) {
            $uuid    = $player->getUniqueId()->toString();
            $profile = $this->sessionManager->getProfile($uuid);

            // Skip players whose profile hasn't loaded yet
            if ($profile === null) {
                continue;
            }

            $nametag = $this->renderer->render($profile);

            // Only update if the nametag actually changed — avoids packet spam
            if ($player->getNameTag() !== $nametag) {
                $player->setNameTag($nametag);
            }
        }
    }
}
