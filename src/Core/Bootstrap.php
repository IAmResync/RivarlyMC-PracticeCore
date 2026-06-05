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
        private readonly \Rivarly\Core\Plugin $plugin
    ) {
    }

    public function init(): \Rivarly\Core\Container
    {
        $container = new \Rivarly\Core\Container($this->plugin);

        $this->registerCommands($container);
        $this->registerListeners($container);
        $this->registerTasks($container);

        return $container;
    }

    private function registerCommands(\Rivarly\Core\Container $container): void {
        $map = $this->plugin->getServer()->getCommandMap();

        $map->register('rivarly', new \Rivarly\Command\QueueCommand(
            $container->queueManager,
            $container->sessionManager,
            $container->gameModeRegistry
        ));

        $map->register('rivarly', new \Rivarly\Command\DuelCommand(
            $container->duelManager,
            $container->sessionManager,
            $container->gameModeRegistry
        ));

        $map->register('rivarly', new \Rivarly\Command\StatsCommand(
            $container->sessionManager
        ));
    }

    private function registerListeners(\Rivarly\Core\Container $container): void {
        $pm = $this->plugin->getServer()->getPluginManager();

        $sumoMode = $container->gameModeRegistry->getMode('sumo');

        $isSumoPlayer = function (string $uuid) use ($container): bool {
            $match = $container->matchManager->getMatchByPlayer($uuid);
            if ($match === null) return false;

            return $match->getGameMode() instanceof SumoMode;
        };

        $pm->registerEvents(
            new \Rivarly\Listener\PlayerListener(
                $container->sessionManager,
                $container->queueManager,
                $container->matchManager,
                $container->hotBarManager,
                $container->scoreboardManager
            ),
            $this->plugin
        );

        $reachChecker = new \Rivarly\AntiCheat\ReachChecker($container->config);
        $cpsLimiter = new \Rivarly\AntiCheat\CpsLimiter($container->config);
        $hitValidator = new \Rivarly\AntiCheat\HitValidator($reachChecker, $cpsLimiter, $container->matchManager);

        $pm->registerEvents(
            new \Rivarly\Listener\CombatListener(
                $container->knockbackEngine,
                $container->matchManager,
                $container->sessionManager,
                $container->statsCollector,
                $hitValidator
            ),
            $this->plugin
        );

        $pm->registerEvents(
            new \Rivarly\Listener\PacketListener(
                $container->matchManager,
                $container->statsCollector
            ),
            $this->plugin
        );

        $pm->registerEvents(
            new \Rivarly\Listener\WorldListener(
                $container->matchManager
            ),
            $this->plugin
        );

        $pm->registerEvents(
            new \Rivarly\Application\Ability\AbilityListener(
                $container->abilityRegistry,
                $container->abilityCooldownManager,
                $container->matchManager,
                $container->sessionManager
            ),
            $this->plugin
        );

        if ($sumoMode instanceof \Rivarly\GameMode\Sumo\SumoMode) {
            $pm->registerEvents(
                new \Rivarly\GameMode\Sumo\SumoListener(
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

    private function registerTasks(\Rivarly\Core\Container $container): void {
        $scheduler = $this->plugin->getScheduler();

        $scheduler->scheduleRepeatingTask(
            new \Rivarly\Task\MatchTickTask($container->matchManager),
            20
        );

        $scheduler->scheduleRepeatingTask(
            new \Rivarly\Task\QueueTickTask(
                $container->queueManager,
                new \Rivarly\Application\Matchmaking\Matchmaker($container->queueManager),
                $container->matchManager,
                $container->sessionManager,
                $container->playerRepository
            ),
            20
        );

        $scheduler->scheduleRepeatingTask(
            new \Rivarly\Task\TournamentTickTask($container->tournamentManager),
            20
        );

        $scheduler->scheduleRepeatingTask(
            new \Rivarly\Task\StatsFlushTask(
                $container->sessionManager,
                $container->playerRepository
            ),
            600
        );
    }
}
