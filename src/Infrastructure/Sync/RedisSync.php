<?php

declare(strict_types=1);

namespace Infrastructure\Sync;

use Infrastructure\Cache\RedisClient;

/**
 * TODO: Zaawansowana synchronizacja oparta na systemie Pub/Sub w Redisie.
 * Przesyła informacje o meczach i statusach graczy pomiędzy wieloma serwerami.
 * Pozwala na budowę spójnego ekosystemu Practice na wielu instancjach PocketMine.
 */
class RedisSync implements ServerSyncInterface {

    private RedisClient $redisClient;
    private string $registryKey = "rivarly:servers";

    /**
     * Wstrzykujemy nasz działający RedisClient.
     */
    public function __construct(RedisClient $redisClient) {
        $this->redisClient = $redisClient;
    }

    /**
     * Rejestruje instancję serwera w Redisie przy użyciu Hashsetu z czasem wygasania (TTL).
     */
    public function registerServer(string $serverId, array $serverData): void {
        if (!$this->redisClient->isEnabled()) {
            return;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            // Zapisujemy dane serwera jako JSON wewnątrz głównego Hasha serwerów
            $redis->hSet($this->registryKey, $serverId, json_encode($serverData), JSON_THROW_ON_ERROR);
        } catch (\Exception $exception) {
            // Ignorujemy błędy sieciowe
        }
    }

    /**
     * Usuwa instancję serwera z globalnego rejestru (np. podczas wyłączenia).
     */
    public function unregisterServer(string $serverId): void {
        if (!$this->redisClient->isEnabled()) {
            return;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            $redis->hDel($this->registryKey, $serverId);
        } catch (\RedisException $exception) {
            // Ignorujemy błędy
        }
    }

    /**
     * Wysyła wiadomość do sieci na określony kanał przy użyciu Pub/Sub.
     */
    public function publishMessage(string $channel, array $payload): void {
        if (!$this->redisClient->isEnabled()) {
            return;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            // Zmieniamy tablicę PHP na string JSON i pushujemy do Redisa
            $message = json_encode($payload, JSON_THROW_ON_ERROR);
            $redis->publish($channel, $message);
        } catch (\Exception $exception) {
            // Bezpieczeństwo ponad wszystko - błąd Redisa nie może wywalić serwera
        }
    }

    /**
     * Rejestruje subskrybcję kanału.
     * UWAGA: W środowisku produkcyjnym NoMercy i Resync muszą wywołać tę metodę wewnątrz AsyncTask/Workera,
     * ponieważ funkcja $redis->subscribe() jest funkcją blokującą!
     */
    public function subscribeToChannel(string $channel, callable $callback): void {
        if (!$this->redisClient->isEnabled()) {
            return;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            // Redis będzie nasłuchiwał wiadomości i gdy jakaś wpadnie, zdekoduje JSON i odpali callback
            $redis->subscribe([$channel], function (\Redis $redis, string $chan, string $msg) use ($callback) : void {
                try {
                    $payload = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($payload)) {
                        $callback($payload);
                    }
                } catch (\JsonException $exception) {
                    // Ignorujemy uszkodzone pakiety JSON
                }
            });
        } catch (\RedisException $exception) {
            // Ignorujemy błędy połączenia
        }
    }

    /**
     * Szybka aktualizacja wybranego parametru serwera (np. zmiana graczy online z 12 na 13).
     */
    public function updateStatus(string $serverId, string $key, mixed $value): void {
        if (!$this->redisClient->isEnabled()) {
            return;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            $currentDataRaw = $redis->hGet($this->registryKey, $serverId);
            $currentData = $currentDataRaw !== false ? json_decode($currentDataRaw, true): [];

            $currentData[$key] = $value;

            $redis->hSet($this->registryKey, $serverId, json_encode($currentData));
        } catch (\Exception $exception) {
            // Ignorujemy błędy
        }
    }
}
