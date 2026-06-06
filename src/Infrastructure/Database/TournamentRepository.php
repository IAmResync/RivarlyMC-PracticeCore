<?php

declare(strict_types=1);

namespace Infrastructure\Database;

use poggit\libasynql\DataConnector;
use Domain\Tournament\Tournament;

/**
 * Zarządza zapisem wyników turniejów oraz listą zwycięzców historycznych.
 */
class TournamentRepository {

    private DataConnector $database;

    public function __construct(DatabaseManager $databaseManager) {
        $this->database = $databaseManager->getConnector();
    }

    /**
     * Inicjalizuje tabelę turniejów w bazie danych.
     */
    public function initTable(): void {
        $this->database->executeGeneric("rivarly.practice.tournaments.init");
    }

    /**
     * Asynchronicznie zapisuje fakt uruchomienia turnieju.
     */
    public function logTournamentStart(Tournament $tournament): void {
        $this->database->executeInsert(
            "rivarly.practice.tournaments.log_start",
            [
                "id"                 => $tournament->getId(),
                "name"               => $tournament->getName(),
                "game_mode"          => $tournament->getGameModeName(),
                "participants_count" => count($tournament->getParticipants())
            ]
        );
    }

    /**
     * Asynchronicznie aktualizuje turniej, dopisując ostatecznego zwycięzcę.
     */
    public function saveTournamentWinner(string $tournamentId, string $winnerName): void {
        // UPDATE – używamy executeGeneric, nie executeInsert
        $this->database->executeGeneric(
            "rivarly.practice.tournaments.set_winner",
            [
                "id"     => $tournamentId,
                "winner" => strtolower($winnerName)
            ]
        );
    }
}
