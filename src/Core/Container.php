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
use GameMode\Boxing\BoxingConfig;
use GameMode\Boxing\BoxingMode;
use GameMode\BuildUHC\BuildUHCMode;
use GameMode\GameModeRegistry;
use GameMode\Nodebuff\NodebuffMode;
use GameMode\Soup\SoupMode;
use GameMode\Sumo\SumoConfig;
use GameMode\Sumo\SumoMode;
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
 * Prosty kontener DI — buduje i wstrzykuje wszystkie zależności pluginu.
 */
class Container {

    public readonly \Application\Match\MatchManager $matchManager;
    public readonly \Application\Matchmaking\QueueManager $queueManager;
    public readonly \Application\Player\SessionManager $sessionManager;
    public readonly \GameMode\GameModeRegistry $gameModeRegistry;
    public readonly \Application\Player\StatsCollector $statsCollector;
    public readonly \Infrastructure\Database\MatchRepository $matchRepository;
    public readonly \Infrastructure\Database\TournamentRepository $tournamentRepository;
    public readonly \Infrastructure\Http\WebhookDispatcher $webhookDispatcher;
    public readonly \Infrastructure\Database\PlayerRepository $playerRepository;
    public readonly \Infrastructure\Database\DatabaseManager $databaseManager;
    public readonly \Application\Season\SeasonManager $seasonManager;
    public readonly \Application\Season\SeasonResetService $seasonResetService;
    public readonly \Application\Season\SeasonRewardRule $seasonRewardRule;
    public readonly \Duel\DuelManager $duelManager;
    public readonly \Party\PartyManager $partyManager;
    public readonly \Presentation\HotBar\HotBarManager $hotBarManager;
    public readonly \Presentation\Kit\KitManager $kitManager;
    public readonly \Presentation\Kit\KitRegistry $kitRegistry;
    public readonly \Presentation\Scoreboard\ScoreboardManager $scoreboardManager;
    public readonly \Presentation\Scoreboard\ScoreboardRenderer $renderer;
    public readonly \Application\Tournament\TournamentManager $tournamentManager;
    public readonly \Application\Ability\AbilityCooldownManager $abilityCooldownManager;
    public readonly \Application\Ability\AbilityRegistry $abilityRegistry;
    public readonly \Combat\KnockbackEngine $knockbackEngine;
    public readonly \Config\PluginConfig $config;

    private \Core\Plugin $plugin;

    public function __construct(\Core\Plugin $plugin) {
        $this->plugin = $plugin;

        // Podstawowe serwisy
        $this->kitManager             = new \Presentation\Kit\KitManager();
        $this->kitRegistry            = new \Presentation\Kit\KitRegistry();
        $this->abilityCooldownManager = new \Application\Ability\AbilityCooldownManager();
        $this->abilityRegistry        = new \Application\Ability\AbilityRegistry();
        $this->config                 = new \Config\PluginConfig($this->plugin);
        $this->knockbackEngine        = new \Combat\KnockbackEngine($this->config);
        $this->renderer               = new \Presentation\Scoreboard\ScoreboardRenderer();
        $this->scoreboardManager      = new \Presentation\Scoreboard\ScoreboardManager($this->renderer);
        $this->webhookDispatcher      = new \Infrastructure\Http\WebhookDispatcher($this->plugin);
        $this->statsCollector         = new \Application\Player\StatsCollector();
        $this->tournamentManager      = new \Application\Tournament\TournamentManager();
        $this->hotBarManager          = new \Presentation\HotBar\HotBarManager();
        $this->partyManager           = new \Party\PartyManager();
        $this->seasonRewardRule       = new \Application\Season\SeasonRewardRule();

        // Rejestr trybów gry — rejestrujemy wszystkie dostępne tryby
        $this->gameModeRegistry = new \GameMode\GameModeRegistry();
        $this->gameModeRegistry->registerMode(new \GameMode\Nodebuff\NodebuffMode());
        $this->gameModeRegistry->registerMode(new \GameMode\Sumo\SumoMode(new \GameMode\Sumo\SumoConfig()));
        $this->gameModeRegistry->registerMode(new \GameMode\Boxing\BoxingMode(new \GameMode\Boxing\BoxingConfig()));
        $this->gameModeRegistry->registerMode(new \GameMode\Soup\SoupMode());
        $this->gameModeRegistry->registerMode(new \GameMode\BuildUHC\BuildUHCMode());

        // Baza danych
        $this->databaseManager = new \Infrastructure\Database\DatabaseManager($this->plugin);

        // Repozytoria
        $this->matchRepository      = new \Infrastructure\Database\MatchRepository($this->databaseManager);
        $this->tournamentRepository = new \Infrastructure\Database\TournamentRepository($this->databaseManager);
        $this->playerRepository     = new \Infrastructure\Database\PlayerRepository($this->databaseManager);

        // Inicjalizacja tabel — MUSI być przed pierwszym zapytaniem
        $this->playerRepository->initTable();
        $this->matchRepository->initTable();
        $this->tournamentRepository->initTable();

        // Czekamy aż libasynql wykona CREATE TABLE queries
        $this->databaseManager->getConnector()->waitAll();

        // Managery wymagające DB
        $this->matchManager      = new \Application\Match\MatchManager(
            $this->statsCollector,
            $this->matchRepository,
            $this->webhookDispatcher
        );
        $this->seasonResetService = new \Application\Season\SeasonResetService(
            $this->playerRepository,
            $this->seasonRewardRule
        );
        $this->seasonManager  = new \Application\Season\SeasonManager($this->seasonResetService);
        $this->duelManager    = new \Duel\DuelManager($this->matchManager, $this->gameModeRegistry);
        $this->sessionManager = new \Application\Player\SessionManager(
            $this->playerRepository,
            $this->plugin->getLogger()
        );
        $this->queueManager   = new \Application\Matchmaking\QueueManager();
    }
}
