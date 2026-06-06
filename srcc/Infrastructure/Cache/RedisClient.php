<?php

declare(strict_types=1);

namespace Infrastructure\Cache;

use Core\Plugin;

/**
 * TODO: Obsługuje szybkie połączenie z pamięcią podręczną Redis.
 * Wykorzystywany do przechowywania danych tymczasowych wymagających niskich opóźnień.
 * Zapewnia wysoką wydajność przy operacjach częstego odczytu i zapisu.
 */
class RedisClient {

    private ?\Redis $redis = null;
    private bool $enabled = false;

    /**
     * Konstruktor klienta Redis.
     * Pobiera dane dostępowe bezpośrednio z przygotowanego wcześniej config.yml.
     */
    public function __construct(Plugin $plugin) {
        $config = $plugin->getConfig()->get("redis", []);

        $this->enabled = (bool)($config["enabled"] ?? false);
        if (!$this->enabled) {
            return;
        }

        // Sprawdzamy, czy środowisko PHP posiada zainstalowane rozszerzenie Redis.
        if (!class_exists(\Redis::class)) {
            $plugin->getLogger()->warning("Rozszerzenie 'ext-redis' nie jest zainstalowane! Funkcje Redisa zostały wyłączone.");
            $this->enabled = false;
            return;
        }

        try {
             $this->redis = new \Redis();

             // Łączymy z limitem czasu (timeout) na 2.5 sekundy, żeby nie zawiesić startu.
            $this->redis->connect(
                $config["host"] ?? "127.0.0.1",
                (int)($config["port"] ?? 6379),
                2.5
            );

            if (!empty($config["password"])) {
                $this->redis->auth($config["password"]);
            }

            if (isset($config["database"])) {
                $this->redis->select((int)$config["database"]);
            }

            $plugin->getLogger()->info("Pomyślnie połączono z serwerem Redis!");
        } catch (\RedisException $exception) {
            $plugin->getLogger()->error("Błąd połączenia z Redisem: " . $exception->getMessage());
            $this->redis = null;
            $this->enabled = false;
        }
    }

    /**
     * Czy Redis jest skonfigurowany i działa poprawnie.
     */
    public function isEnabled(): bool {
        return $this->enabled && $this->redis !== null;
    }

    /**
     * Zwraca surową instancję klasy Redis do operacji pamięciowych.
     */
    public function getNativeClient(): ?\Redis {
        return $this->redis;
    }

    /**
     * Bezpiecznie zapisywanie wartości do cache z opcjonalnym czasem wygaśnięcia (TTL).
     */
    public function set($key, string $value, int $ttl = 0): bool {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $value);
            }
            return $this->redis->set($key, $value);
        } catch (\RedisException $exception) {
            return false;
        }
    }

    /**
     * Rozłącza klienta przy wyłączeniu wtyczki (onDisable).
     */
    public function close(): void {
        if ($this->redis !== null) {
            try {
                $this->redis->close();
            } catch (\RedisException $exception) {
                // Ignorujemy błędy przy zamykaniu.
            }
        }
    }
}
