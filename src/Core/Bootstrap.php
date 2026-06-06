<?php

declare(strict_types=1);

namespace Core;

use Application\Ability\AbilityListener;
use Core\Plugin;
use GameMode\Sumo\SumoListener;
use GameMode\Sumo\SumoMode;
use Listener\CombatListener;
use Listener\PacketListener;
use Listener\PlayerListener;
use Listener\WorldListener;
use Task\MatchTickTask;
use Task\QueueTickTask;
use Task\TournamentTickTask;
use Task\StatsFlushTask;
use AntiCheat\HitValidator;
use AntiCheat\ReachChecker;
use AntiCheat\CpsLimiter;
use Application\Matchmaking\Matchmaker;
use Command\DuelCommand;
use Command\QueueCommand;
use Command\StatsCommand;

/**
 * Handles registration of managers, listeners, tasks and commands.
 */
class Bootstrap {

    public function __construct(
        private readonly \Core\Plugin $plugin
    ) {
    }

    public function init(): \Core\Container
    {
        $container = new \Core\Container($this->plugin);

        $this->registerCommands($container);
        $this->registerListeners($container);
        $this->registerTasks($container);

        return $container;
    }

    private function registerCommands(\Core\Container $container): void {
        $map = $this->plugin->getServer()->getCommandMap();

        $map->register('rivarly', new \Command\QueueCommand(
            $container->queueManager,
            $container->sessionManager,
            $container->gameModeRegistry
        ));

        $map->register('rivarly', new \Command\DuelCommand(
            $container->duelManager,
            $container->sessionManager,
            $container->gameModeRegistry
        ));

        $map->register('rivarly', new \Command\StatsCommand(
            $container->sessionManager
        ));
    }

    private function registerListeners(\Core\Container $container): void {
        $pm = $this->plugin->getServer()->getPluginManager();

        $sumoMode = $container->gameModeRegistry->getMode('sumo');

        $isSumoPlayer = function (string $uuid) use ($container): bool {
            $match = $container->matchManager->getMatchByPlayer($uuid);
            if ($match === null) return false;

            return $match->getGameMode() instanceof SumoMode;
        };

        $pm->registerEvents(
            new \Listener\PlayerListener(
                $container->sessionManager,
                $container->queueManager,
                $container->matchManager,
                $container->hotBarManager,
                $container->scoreboardManager
            ),
            $this->plugin
        );

        $reachChecker = new \AntiCheat\ReachChecker($container->config);
        $cpsLimiter = new \AntiCheat\CpsLimiter($container->config);
        $hitValidator = new \AntiCheat\HitValidator($reachChecker, $cpsLimiter, $container->matchManager);

        $pm->registerEvents(
            new \Listener\CombatListener(
                $container->knockbackEngine,
                $container->matchManager,
                $container->sessionManager,
                $container->statsCollector,
                $hitValidator
            ),
            $this->plugin
        );

        $pm->registerEvents(
            new \Listener\PacketListener(
                $container->matchManager,
                $container->statsCollector
            ),
            $this->plugin
        );

        $pm->registerEvents(
            new \Listener\WorldListener(
                $container->matchManager
            ),
            $this->plugin
        );

        $pm->registerEvents(
            new \Application\Ability\AbilityListener(
                $container->abilityRegistry,
                $container->abilityCooldownManager,
                $container->matchManager,
                $container->sessionManager
            ),
            $this->plugin
        );

        if ($sumoMode instanceof \GameMode\Sumo\SumoMode) {
            $pm->registerEvents(
                new \GameMode\Sumo\SumoListener(
                    $sumoMode,
                    $container->matchManager,
                    $container->sessionManager,
                    $container->knockbackEngine,
                    $isSumoPlayer
                ),
                $this->plugin
            );
        }
    }

    private function registerTasks(\Core\Container $container): void {
        $scheduler = $this->plugin->getScheduler();

        $scheduler->scheduleRepeatingTask(
            new \Task\MatchTickTask($container->matchManager),
            20
        );

        $scheduler->scheduleRepeatingTask(
            new \Task\QueueTickTask(
                $container->queueManager,
                new \Application\Matchmaking\Matchmaker($container->queueManager),
                $container->matchManager,
                $container->sessionManager,
                $container->playerRepository
            ),
            20
        );

        $scheduler->scheduleRepeatingTask(
            new \Task\TournamentTickTask($container->tournamentManager),
            20
        );

        $scheduler->scheduleRepeatingTask(
            new \Task\StatsFlushTask(
                $container->sessionManager,
                $container->playerRepository
            ),
            600
        );
    }
}
