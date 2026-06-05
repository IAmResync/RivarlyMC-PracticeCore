<?php

declare(strict_types=1);

namespace Infrastructure\Database;

use poggit\libasynql\DataConnector;
use Domain\Player\PlayerProfile;
use Domain\Player\PerModeStats;
use Domain\Player\EloHistoryEntry;
use Domain\Player\Division;

/**
 * Repozytorium do zapisu i odczytu profili graczy z bazy danych.
 * Obsługuje zarówno SQLite jak i MySQL przez DatabaseManager.
 *
 * Wzorzec: Repository Pattern — Application layer nigdy nie dotyka
 * DatabaseManager bezpośrednio, wszystko idzie przez to repozytorium.
 *
 * Wszystkie metody są synchroniczne (PocketMine nie ma async ORM).
 * Zapis gracza powinien być wywołany przez StatsFlushTask co N sekund,
 * nie przy każdym zabójstwie.
 */
final class PlayerRepository {

    private DataConnector $database;

    /**
     * Wstrzykujemy konektor bazy danych (najlepiej przez konektor DI).
     */
    public function __construct(DatabaseManager $databaseManager) {
        $this->database = $databaseManager->getConnector();
    }

    /**
     * Tworzy tabelę w bazie danych przy starcie serwera, jeśli jeszcze nie istnieje.
     */
    public function initTable(): void {
        $this->database->executeGeneric("rivarly.practice.players.init");
    }

    /**
     * Asynchronicznie ładuje profil gracza z bazy danych.
     * Ponieważ to operacja asynchroniczna, wynik przekazujemy przez funkcję zwrotną (callable).
     *
     * @param string $playerName
     * @param callable $onSuccess Funkcja przyjmująca array{elo: int, kills: int, deaths: int}
     * @param callable $onError Funkcja wywoływana w razie błędu bazy danych
     */
    public function loadProfile(string $playerName, callable $onSuccess, callable $onError): void {
        $username = strtolower($playerName);

        $this->database->executeSelect(
            "rivarly.practice.players.load",
            ["username" => $username],
            function (array $rows) use ($onSuccess): void {
                if (empty($rows)) {
                    // Gracz wchodzi pierwszy raz - zwracamy wartości domyślne
                    $onSuccess([
                        "elo" => 1000,
                        "kills" => 0,
                        "deaths" => 0
                    ]);
                    return;
                }

                // Przekazujemy wczytany rekord z bazy danych wyżej
                $onSuccess($rows[0]);
            },
            $onError
        );
    }

    /**
     * Asynchronicznie zapisuje (lub aktualizuje) statystyki gracza w bazie danych.
     */
    public function saveProfile(string $uuid, string $xuid, string $username, int $elo, int $kills, int $deaths): void {
        $usernameLower = strtolower($username);

        $this->database->executeInsert(
            "rivarly.practice.players.save",
            [
                "uuid" => $uuid,
                "xuid" => $xuid,
                "username" => $usernameLower,
                "global_elo" => $elo,
                "global_kills" => $kills,
                "global_deaths" => $deaths
            ]
        );
    }
}
            ]
        );
    }
}
