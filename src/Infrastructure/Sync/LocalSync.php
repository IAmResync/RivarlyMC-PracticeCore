<?php

declare(strict_types=1);

namespace Infrastructure\Sync;

/**
 * TODO: Implementacja synchronizacji dla środowisk jedno-serwerowych.
 * Działa lokalnie bez wysyłania pakietów do zewnętrznych systemów kolejkowych.
 * Zapewnia kompatybilność interfejsu przy zachowaniu minimalnego narzutu.
 */
class LocalSync implements ServerSyncInterface {
    /** @var array<string, array<string, mixed>> Przechowuje dane "sieciowe" w lokalnej pamięci RAM */
    private array $localServerRegistry = [];

    /** @var array<string, array<int, callable>> Rejestr lokalnych subskrybentów na wiadomości */
    private array $localSubscribers = [];

    /**
     * Rejestruje obecną instancję lokalnie.
     */
    public function registerServer(string $serverId, array $serverData): void {
        $this->localServerRegistry[$serverId] = $serverData;
    }

    /**
     * Wyrejestrowuje serwer z lokalnej pamięci.
     */
    public function unregisterServer(string $serverId): void {
        unset($this->localServerRegistry[$serverId]);
    }

    /**
     * Ponieważ to pojedyńczy serwer (Local), wysyłanie wiadomości natychmiast
     * przekazuje ją do lokalnych słuchaczy w tym samym procesie PHP.
     */
    public function publishMessage(string $channel, array $payload): void {
        if (!isset($this->localServerRegistry[$channel])) {
            return;
        }

        foreach ($this->localSubscribers[$channel] as $callback) {
            // Bezpośrednie wywoływanie kodu nasłuchującego.
            $callback($payload);
        }
    }

    /**
     * Rejestruje funkcję callback pod dany kanał w pamięci serwera.
     */
    public function subscribeToChannel(string $channel, callable $callback): void {
        if (!isset($this->localSubscribers[$channel])) {
            $this->localSubscribers[$channel] = [];
        }

        $this->localSubscribers[$channel][] = $callback;
    }

    /**
     * Aktualizuje dane lokalnej instancji.
     */
    public function updateStatus(string $serverId, string $key, mixed $value): void {
        if (isset($this->localServerRegistry[$serverId])) {
            $this->localServerRegistry[$serverId][$key] = $value;
        }
    }
}
