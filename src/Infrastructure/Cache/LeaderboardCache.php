<?php

declare(strict_types=1);

namespace Infrastructure\Cache;

/**
 * TODO: Zarządza rankingiem graczy wyświetlanym w czasie rzeczywistym.
 * Wykorzystuje strukturę Sorted Sets w Redisie do błyskawicznego sortowania graczy.
 * Pozwala na pobieranie topowych graczy bez obciążania głównej bazy danych.
 */
class LeaderboardCache {

    private RedisClient $redisClient;
    private string $redisKey = "rivarly:leaderboard:elo";

    /**
     * Wstrzykujemy nasz wcześniej przygotowany RedisClient.
     */
    public function __construct(RedisClient $redisClient) {
        $this->redisClient = $redisClient;
    }

    /**
     * Aktualizuje pozycję gracza w rankingu ELO.
     * Wywoływane asynchronicznie po każdej zakończonej walce.
     *
     * @param string $playerName Nick gracza
     * @param int $newElo Nowe punkty ELO po walce
     */
    public function updateElo(string $playerName, int $newElo): void {
        if (!$this->redisClient->isEnabled()) {
            return;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            // Komenda ZADD dodaje gracza lub aktualizuje jego wynik (score) w zbiorze sorted set.
            $redis->zAdd($this->redisKey, $newElo, strtolower($playerName));
        } catch (\RedisException $exception) {
            // Ignorujemy błędy sieciowe Redisa, żeby nie scrashować rozgrywki.
        }
    }

    /**
     * Pobiera najlepszych graczy z rankingu.
     * Idealne do wyświetlenia topki na hologramach, tablicy lub przekazania do API Next.js.
     *
     * @param int $limit Liczba graczy do pobrania (np. 10 dla TOP 10)
     * @return array<string, int> Tablica asocjacyjna w formacie [nick => punkty_elo]
     */
    public function getTopPlayers(int $limit = 10): array {
        if (!$this->redisClient->isEnabled()) {
            return [];
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            // zRevRange zwraca elementy od największego do najniższego wyniku.
            // Argument 'true' sprawia, że funkcja zwraca też wartości ELO, a nie tylko same nicki.
            /** @var array<string, float|int>|false $result */
            $result = $redis->zRevRange($this->redisKey, 0, $limit - 1, true);

            if ($result === false) {
                return [];
            }

            // Rzutujemy wyniki na int dla pewności i czystości danych.
            $leaderboard = [];
            foreach ($result as $player => $elo) {
                $leaderboard[(string)$player] = (int)$elo;
            }
            return $leaderboard;
        } catch (\RedisException $exception) {
            return [];
        }
    }

    /**
     * Pobiera aktualną pozycję konkretnego gracza w globalnym rankingu (np. "Twoja pozycja: #45").
     *
     * @return int|null Zwraca pozycję (indeksowaną od 1) lub null, jeśli gracz nie jest w rankingu.
     */
    public function getPlayerRank(string $playerName): ?int {
        if (!$this->redisClient->isEnabled()) {
            return null;
        }

        $redis = $this->redisClient->getNativeClient();
        try {
            // zRevRank zwraca pozycje gracza (od najwyższego), indeksowaną od 0.
            $rank = $redis->zRevRank($this->redisKey, strtolower($playerName));
            return $rank === false ? null : $rank + 1;
        } catch (\RedisException $exception) {
            return null;
        }
    }
}
