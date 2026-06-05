<?php

declare(strict_types=1);

namespace Infrastructure\Sync;

/**
 * TODO: Definiuje sposób komunikacji między różnymi instancjami serwerów.
 * Pozwala na synchronizację statusów graczy i zdarzeń globalnych w sieci.
 * Jest kluczowy przy skalowaniu pluginu na wiele maszyn fizycznych.
 */
interface ServerSyncInterface {

    /**
     * Rejestruje obecną instancję serwera w globalnej sieci (heartbeat).
     * Wywoływane przy starcie serwera, aby proxy i inne instancje widziały, że ten serwer żyje
     * * @param string $serverId Unikalne ID tego konkretnego serwera (np. "practice-1")
     * @param array<string, mixed> $serverData Dodatkowe dane, np. ilość graczy, wolne areny
     */
    public function registerServer(string $serverId, array $serverData): void;

    /**
     * Wyrejestrowuje serwer z sieci (np. podczas restartu / onDisable).
     */
    public function unregisterServer(string $serverId): void;

    /**
     * Rozsyła globalną wiadomość (pakiet/event) do wszystkich innych instancji serwerów practice
     * Używane np. do globalnego chatu, powiadomień o turniejach, czy banów na całą sieć.
     * * @param string $channel Nazwa kanału informacyjnego (np. "global_chat", "tournament").
     * @param array<string, mixed> $payload Dane przesyłane w formacie klucz-wartość (zostaną zserializowane do JSON)
     */
    public function publishMessage(string $channel, array $payload): void;

    /**
     * Odpala nasłuchiwanie (subskrybcję) na konkretnym kanale sieciowym.
     * Kiedy inny serwer wyśle wiadomość, ten serwer ją przechwyci i wywoła funkcję callback.
     * * @param string $channel Nazwa kanału do nasłuchu
     * @param callable $callback Funkcja uruchamiana po odebraniu wiadomości przyjmująca tablicę z danymi
     */
    public function subscribeToChannel(string $channel, callable $callback): void;

    /**
     * Aktualizuje status serwera "w locie" (np. zmiana liczby graczy online).
     */
    public function updateStatus(string $serverId, string $key, mixed $value): void;
}
