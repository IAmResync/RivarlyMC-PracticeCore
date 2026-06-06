<?php

declare(strict_types=1);

namespace Infrastructure\Database;

use poggit\libasynql\DataConnector;

/**
 * Odpowiada za trwały zapis historii wszystkich rozegranych meczów.
 */
class MatchRepository {

    private DataConnector $database;

    public function __construct(DatabaseManager $databaseManager) {
        $this->database = $databaseManager->getConnector();
    }

    /**
     * Tworzy tabelę historii meczów przy starcie serwera.
     */
    public function initTable(): void {
        $this->database->executeGeneric("rivarly.practice.matches.init");
    }

    /**
     * Asynchronicznie zapisuje zakończony pojedynek do bazy danych.
     */
    public function logMatch(string $matchId, string $gameMode, string $winner, string $loser, int $duration): void {
        $this->database->executeInsert(
            "rivarly.practice.matches.log",
            [
                "id"               => $matchId,
                "game_mode"        => strtolower($gameMode),
                "winner"           => strtolower($winner),
                "loser"            => strtolower($loser),
                "duration_seconds" => $duration   // poprawiona literówka: było duration_secounds
            ]
        );
    }
}
