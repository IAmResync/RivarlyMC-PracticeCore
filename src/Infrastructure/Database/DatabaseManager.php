<?php

declare(strict_types=1);

namespace Infrastructure\Database;

use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use Core\Plugin;

/**
 * Zarządza połączeniem z bazą danych (SQLite3 lub MySQL).
 * Tworzy wszystkie tabele jeśli nie istnieją i udostępnia jedno
 * globalne połączenie dla repozytoriów.
 *
 * Tryb SQLite → domyślny, zero konfiguracji, idealny do testów.
 * Tryb MySQL  → produkcja, multi-node, włącz w config.yml.
 *
 * Użycie:
 *   $db = DatabaseManager::create($dataPath, $config);
 *   $db->getConnection(); // → SQLite3 | mysqli
 */
final class DatabaseManager {

    private DataConnector $connector;

    /**
     * Konstruktor menadżera bazy danych.
     * Łączymy libasynql przy użyciu sekcji z config.yml serwera.
     */
    public function __construct(\Core\Plugin $plugin) {
        // Wczytujemy sekcję "database" z pliku konfiguracyjnego pluginu.
        $databaseConfig = $plugin->getConfig()->get("database", [
            "type" => "sqlite",
            "sqlite" => ["file" => "sqlite.db"],
            "worker-limit" => 2
        ]);

        // Inicjalizujemy asynchroniczne połączenie (obsługuje SQLite i MySQL bez zmian w kodzie!)
        $this->connector = libasynql::create($plugin, $databaseConfig, [
            "sqlite" => "sqlite.sql",
            "mysql" => "mysql.sql"
        ]);
    }

    /**
     * Zwraca główny konektor do wykonywania asynchronicznych zapytań SQL.
     */
    public function getConnector(): DataConnector {
        return $this->connector;
    }

    /**
     * Wywoływane przy wyłączeniu pluginu (Plugin::onDisable).
     * Zamyka bezpiecznie pulę wątków przy bazy danych, zapobiegając utracie danych graczy.
     */
    public function close(): void {
        if (isset($this->connector)) {
            $this->connector->close();
        }
    }
}