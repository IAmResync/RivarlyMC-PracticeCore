<?php

declare(strict_types=1);

namespace Domain\GameMode;

/**
 * TODO: Definiuje kontrakt, który musi spełnić każdy tryb gry w pluginie.
 * Określa metody wymagane do inicjalizacji trybu, wczytania ekwipunku i zasad walki.
 * Pozwala na łatwe dodawanie nowych trybów bez modyfikacji głównej logiki.
 */
interface GameModeInterface {
    /**
     * Unikalna nazwa indentyfikacyjna trybu (np. "nodebuff").
     */
    public function getName(): string;

    /**
     * Zwraca obiekt konfiguracyjny danego trybu (czas meczu, niestandardowe HP, etc.).
     */
    public function getConfig(): GameModeConfig;

    /**
     * Zwraca strukture przedmiotów (kitu), jakie gracz ma otrzymać po starcie meczu.
     * Zwraca tablicę par [slot_id => dane_przedmiotu] lub dedykowany obiekt w czystym php.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInventoryTemplate(): array;

    /**
     * Zwraca strukture przedmiotów (zbroi) dla danego trybu (hełm, klata, spodnie, buty.).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getArmorTemplate(): array;

    /**
     * Zwraca listę efektów mikstur, które gracz otrzymuje na stałe w tym trybie (np Speed II w Nodebuff.).
     *
     * @return array<string, int> Tabela w formacie ["nazwa_danego_efektu" => poziom_wzmocnienia_efektu]
     */
    public function getMatchLongEffects(): array;

    /**
     * Czytelna nazwa wyświetlana w menu GUI dla graczy ("Nodebuff".).
     */
    public function getDisplayName(): string;

    //============================================================
    // LIFECYCLE METHODS (Metody cyklu życia meczu)
    //============================================================

    /**
     * Wywoływane w momencie faktycznego rozpoczęcia meczu (np. po zakończeniu odliczania.).
     * Pozwala trybowi na zainicjowanie specyfikacji zadań
     *
     * @param string $matchId Unikalny indentyfikator instancji meczu z GameMatch
     */
    public function onMatchStart(string $matchId): void;

    /**
     * Wywoływane w momencie śmierci gracza wewnątrz danej instancji meczu.
     * Pozwala na obsługe niestandardowych akcji.
     *
     * @param string $matchId
     * @param string $killerName
     * @param string $victimName
     */
    public function onPlayerDeath(string $matchId, string $killerName, string $victimName): void;

    /**
     * Wywoływanie tuż przed całkowitym usunięciem instancji meczu i zresetowaniem areny.
     * Służy do czyszczenia danych, usuwaniem postawionych bloków, zatrzymywania tasków.
     *
     * @param string $matchId
     */
    public function onMatchEnd(string $matchId): void;
}
