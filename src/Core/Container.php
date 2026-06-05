<?php

declare(strict_types=1);

namespace Core;

use Application\Ability\AbilityCooldownManager;
use Application\Ability\AbilityRegistry;
use Combat\KnockbackEngine;
use Application\Match\MatchManager;
use Application\Matchmaking\QueueManager;
use Application\Player\SessionManager;
use Application\Player\StatsCollector;
use Application\Season\SeasonManager;
use Application\Season\SeasonResetService;
use Application\Season\SeasonRewardRule;
use Application\Tournament\TournamentManager;
use Config\PluginConfig;
use Duel\DuelManager;
use GameMode\GameModeRegistry;
use Infrastructure\Database\DatabaseManager;
use Infrastructure\Database\MatchRepository;
use Infrastructure\Database\PlayerRepository;
use Infrastructure\Database\TournamentRepository;
use Infrastructure\Http\WebhookDispatcher;
use Party\PartyManager;
use Presentation\HotBar\HotBarManager;
use Presentation\Kit\KitManager;
use Presentation\Kit\KitRegistry;
use Presentation\Scoreboard\ScoreboardManager;
use Presentation\Scoreboard\ScoreboardRenderer;

/**
 * Prosty kontener DI.
 */
class Container {

    public readonly \Rivarly\Application\Match\MatchManager $matchManager;
    public readonly \Rivarly\Application\Matchmaking\QueueManager $queueManager;
    public readonly \Rivarly\Application\Player\SessionManager $sessionManager;
    public readonly \Rivarly\GameMode\GameModeRegistry $gameModeRegistry;
    public readonly \Rivarly\Application\Player\StatsCollector $statsCollector;
    public readonly \Rivarly\Infrastructure\Database\MatchRepository $matchRepository;
    public readonly \Rivarly\Infrastructure\Database\TournamentRepository $tournamentRepository;
    public readonly \Rivarly\Infrastructure\Http\WebhookDispatcher $webhookDispatcher;
    public readonly \Rivarly\Infrastructure\Database\PlayerRepository $playerRepository;
    public readonly \Rivarly\Infrastructure\Database\DatabaseManager $databaseManager;
    public readonly \Rivarly\Application\Season\SeasonManager $seasonManager;
    public readonly \Rivarly\Application\Season\SeasonResetService $seasonResetService;
    public readonly \Rivarly\Application\Season\SeasonRewardRule $seasonRewardRule;
    public readonly \Rivarly\Duel\DuelManager $duelManager;
    public readonly \Rivarly\Party\PartyManager $partyManager;
    public readonly \Rivarly\Presentation\HotBar\HotBarManager $hotBarManager;
    public readonly \Rivarly\Presentation\Kit\KitManager $kitManager;
    public readonly \Rivarly\Presentation\Kit\KitRegistry $kitRegistry;
    public readonly \Rivarly\Presentation\Scoreboard\ScoreboardManager $scoreboardManager;
    public readonly \Rivarly\Presentation\Scoreboard\ScoreboardRenderer $renderer;
    public readonly \Rivarly\Application\Tournament\TournamentManager $tournamentManager;
    public readonly \Rivarly\Application\Ability\AbilityCooldownManager $abilityCooldownManager;
    public readonly \Rivarly\Application\Ability\AbilityRegistry $abilityRegistry;
    public readonly \Rivarly\Combat\KnockbackEngine $knockbackEngine;
    public readonly \Rivarly\Config\PluginConfig $config;
    private \Rivarly\Core\Plugin $plugin;

    public function __construct(\Rivarly\Core\Plugin $plugin){
        $this->plugin = $plugin;
        $this->kitManager = new \Rivarly\Presentation\Kit\KitManager();
        $this->kitRegistry = new \Rivarly\Presentation\Kit\KitRegistry();
        $this->abilityCooldownManager = new \Rivarly\Application\Ability\AbilityCooldownManager();
        $this->abilityRegistry = new \Rivarly\Application\Ability\AbilityRegistry();
        $this->config = new \Rivarly\Config\PluginConfig($this->plugin);
        $this->knockbackEngine = new \Rivarly\Combat\KnockbackEngine($this->config);
        $this->renderer = new \Rivarly\Presentation\Scoreboard\ScoreboardRenderer();
        $this->scoreboardManager = new \Rivarly\Presentation\Scoreboard\ScoreboardManager($this->renderer);
        $this->webhookDispatcher = new \Rivarly\Infrastructure\Http\WebhookDispatcher($this->plugin);
        $this->databaseManager = new \Rivarly\Infrastructure\Database\DatabaseManager($this->plugin);
        $this->gameModeRegistry = new \Rivarly\GameMode\GameModeRegistry();
        $this->statsCollector = new \Rivarly\Application\Player\StatsCollector();
        $this->tournamentManager = new \Rivarly\Application\Tournament\TournamentManager();
        $this->hotBarManager = new \Rivarly\Presentation\HotBar\HotBarManager();
        $this->partyManager = new \Rivarly\Party\PartyManager();
        $this->seasonRewardRule = new \Rivarly\Application\Season\SeasonRewardRule();

        // Repozytoria
        $this->matchRepository = new \Rivarly\Infrastructure\Database\MatchRepository($this->databaseManager);
        $this->tournamentRepository = new \Rivarly\Infrastructure\Database\TournamentRepository($this->databaseManager);
        $this->playerRepository = new \Rivarly\Infrastructure\Database\PlayerRepository($this->databaseManager);

        // Inicjalizacja tabel bazy danych - MUSI być przed pierwszym użyciem
        $this->playerRepository->initTable();
        $this->matchRepository->initTable();
        $this->tournamentRepository->initTable();

        // Synchronizacja - czekamy aż libasynql wykona kolejkę CREATE TABLE
        $this->databaseManager->getConnector()->waitAll();

        $this->matchManager = new \Rivarly\Application\Match\MatchManager($this->statsCollector, $this->matchRepository, $this->webhookDispatcher);
        $this->seasonResetService = new \Rivarly\Application\Season\SeasonResetService($this->playerRepository, $this->seasonRewardRule);
        $this->seasonManager = new \Rivarly\Application\Season\SeasonManager($this->seasonResetService);
        $this->duelManager = new \Rivarly\Duel\DuelManager($this->matchManager, $this->gameModeRegistry);
        $this->sessionManager = new \Rivarly\Application\Player\SessionManager($this->playerRepository, $this->plugin->getLogger());
        $this->queueManager = new \Rivarly\Application\Matchmaking\QueueManager();
    }
}
