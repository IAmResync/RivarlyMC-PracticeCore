<?php

declare(strict_types=1);

namespace Infrastructure\Database;

use poggit\libasynql\DataConnector;

/**
 * Repozytorium do zapisu i odczytu profili graczy z bazy danych.
 * Obsługuje zarówno SQLite jak i MySQL przez DatabaseManager.
 */
final class PlayerRepository {

    private DataConnector $database;

    public function __construct(DatabaseManager $databaseManager) {
        $this->database = $databaseManager->getConnector();
    }

    /**
     * Tworzy tabelę przy starcie serwera.
     */
    public function initTable(): void {
        $this->database->executeGeneric("rivarly.practice.players.init");
    }

    /**
     * Asynchronicznie ładuje profil gracza z bazy danych.
     *
     * @param callable $onSuccess array{uuid: string, xuid: string, username: string, global_elo: int, global_kills: int, global_deaths: int}
     * @param callable $onError
     */
    public function loadProfile(string $playerName, callable $onSuccess, callable $onError): void {
        $username = strtolower($playerName);

        $this->database->executeSelect(
            "rivarly.practice.players.load",
            ["username" => $username],
            function (array $rows) use ($onSuccess): void {
                if (empty($rows)) {
                    $onSuccess([
                        "elo"    => 1000,
                        "kills"  => 0,
                        "deaths" => 0
                    ]);
                    return;
                }
                $row = $rows[0];
                $onSuccess([
                    "elo"    => (int) $row["global_elo"],
                    "kills"  => (int) $row["global_kills"],
                    "deaths" => (int) $row["global_deaths"],
                ]);
            },
            $onError
        );
    }

    /**
     * Asynchronicznie zapisuje (lub aktualizuje) profil gracza.
     */
    public function saveProfile(string $uuid, string $xuid, string $username, int $elo, int $kills, int $deaths): void {
        $this->database->executeInsert(
            "rivarly.practice.players.save",
            [
                "uuid"          => $uuid,
                "xuid"          => $xuid,
                "username"      => strtolower($username),
                "global_elo"    => $elo,
                "global_kills"  => $kills,
                "global_deaths" => $deaths,
            ]
        );
    }
}
