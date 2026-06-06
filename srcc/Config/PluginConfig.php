<?php

declare(strict_types=1);

namespace Config;

use InvalidArgumentException;
use Core\Plugin;

/**
 * Typowany wrapper na config.yml eliminujący surowe getString()/getInt() w całym kodzie.
 * Każde ustawienie ma własną metodę z typem zwracanym (np. getMatchDurationSeconds(): int).
 * Waliduje config przy starcie i rzuca wyjątek jeśli brakuje wymaganego klucza.
 */
final class PluginConfig {

    // Baza danych SQL
    private string $dbType;
    private array $dbCredentials; // Zmieniono na array, ponieważ getNested dla credentials zwraca tablicę danych połączeniowych

    // Redis Cache & Sync
    private string $redisHost;
    private int $redisPort;
    private string $redisPassword;

    // Webhooki WWW (Next.js)
    private bool $webhooksEnabled;
    private string $webhooksUrl;
    private string $webhooksSecret;

    // Discord Notifier
    private bool $discordEnabled;
    private string $discordWebhookUrl;

    // Ustawienia Meczu (Match Settings) - DODANO
    private int $matchCountdownSeconds;
    private int $matchGracePeriodSeconds;
    private int $matchDurationSeconds;

    // Ustawienia KnockBacku - DODANO
    private float $kbHorizontal;
    private float $kbVertical;

    // Ustawienia AntyCheata (Reach, CPS) - DODANO
    private float $maxReach;
    private float $reachBuffer;
    private int $maxCps;

    private \Core\Plugin $plugin;

    public function __construct(\Core\Plugin $plugin) {
        $this->plugin = $plugin;
        $this->load();
    }

        public function load(): void {
            $this->plugin->reloadConfig();
            $config = $this->plugin->getConfig();

            // 1. Walidacja sekcji bazy danych
            $this->dbType = strtolower($config->getNested("database.type", "sqlite"));
            if (!in_array($this->dbType, ["sqlite", "mysql"], true)) {
                throw new InvalidArgumentException("Niepoprawny typ bazy danych '{$this->dbType}'. Dozwolone: sqlite, mysql");
            }
            $this->dbCredentials = (array)$config->getNested("database.credentials", []);

            // 2. Walidacja sekcji Redis
            $this->redisHost = (string)$config->getNested("redis.host", "127.0.0.1");
            $this->redisPort = (int)$config->getNested("redis.port", 6379);
            $this->redisPassword = (string)$config->getNested("redis.password", "");

            // 3. Walidacja sekcji Webhooks
            $this->webhooksEnabled = (bool)$config->getNested("webhooks.enabled", false);
            $this->webhooksUrl = (string)$config->getNested("http.webhooks.api-url", "");
            $this->webhooksSecret = (string)$config->getNested("http.webhooks.secret-token", "");

            if ($this->webhooksEnabled && empty($this->webhooksUrl)) {
                throw new InvalidArgumentException("Webhooki są włączone, ale opcja 'http.webhooks.api-url' jest pusta!");
            }

            // 4. Walidacja sekcji Discord
            $this->discordEnabled = (bool)$config->getNested("http.discord.enabled", true);
            $this->discordWebhookUrl = (string)$config->getNested("http.discord.webhook-url", "");

            if ($this->discordEnabled && empty($this->discordWebhookUrl)) {
                throw new InvalidArgumentException("Powiadomienia Discord są włączone, ale 'http.discord.webhook-url' jest pusty!");
            }

            // 5. Sekcja ustawień rozgrywki (Match Gameplay) - DODANO
            $this->matchCountdownSeconds = (int)$config->getNested("match.countdown-seconds", 5);
            $this->matchGracePeriodSeconds = (int)$config->getNested("match.grace-period-seconds", 3);
            $this->matchDurationSeconds = (int)$config->getNested("match.duration-seconds", 180);

            // 6. Sekcja ustawień fizyki (KnockBack) - DODANO
            $this->kbHorizontal = (float)$config->getNested("knockback.base-horizontal", 0.4);
            $this->kbVertical = (float)$config->getNested("knockback.base-vertical", 0.4);

            // 7. Sekcja Antycheata - DODANO
            $this->maxReach = (float)$config->getNested("antycheat.max-reach", 3.2);
            $this->reachBuffer = (float)$config->getNested("antycheat.reach-buffer", 0.5);
            $this->maxCps = (int)$config->getNested("antycheat.max-cps", 20);
        }

    public function reload(): void {
        $this->load();
    }

    // =============================
    // Gettery dla Bazy Danych
    // =============================

    public function getDatabaseType(): string {
        return $this->dbType;
    }

    public function getDatabaseCredentials(): array {
        return $this->dbCredentials;
    }

    // ==============================
    // Gettery dla Redisa
    // ==============================

    public function getRedisHost(): string {
        return $this->redisHost;
    }

    public function getRedisPort(): int {
        return $this->redisPort;
    }

    public function getRedisPassword(): string {
        return $this->redisPassword;
    }

    // ==================================
    // Gettery dla Webhooków WWW
    // ==================================

    public function areWebhooksEnabled(): bool {
        return $this->webhooksEnabled;
    }

    public function getWebhooksUrl(): string {
        return $this->webhooksUrl;
    }

    public function getWebhooksSecret(): string {
        return $this->webhooksSecret;
    }

    // ===================================
    // Gettery dla Discord
    // ===================================

    public function isDiscordEnabled(): bool {
        return $this->discordEnabled;
    }

    public function getDiscordWebhookUrl(): string {
        return $this->discordWebhookUrl;
    }

    // ===================================
    // Gettery dla Rozgrywki (Match) - DODANO
    // ===================================

    public function getCountdownSeconds(): int {
        return $this->matchCountdownSeconds;
    }

    public function getGracePeriodSeconds(): int {
        return $this->matchGracePeriodSeconds;
    }

    public function getMatchDurationSeconds(): int {
        return $this->matchDurationSeconds;
    }

    // ===================================
    // Gettery dla Fizyki (KnockBack) - DODANO
    // ===================================

    public function getKnockbackHorizontal(): float {
        return $this->kbHorizontal;
    }

    public function getKnockbackVertical(): float {
        return $this->kbVertical;
    }

    // ===================================
    // Gettery dla Antycheata - DODANO
    // ===================================

    public function getMaxReach(): float {
        return $this->maxReach;
    }

    public function getReachSuspiciousBuffer(): float {
        return $this->reachBuffer;
    }

    public function getMaxCps(): int {
        return $this->maxCps;
    }
}