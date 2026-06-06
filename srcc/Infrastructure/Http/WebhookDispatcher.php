<?php

declare(strict_types=1);

namespace Infrastructure\Http;

use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use Core\Plugin;

/**
 * Odpowiada za wysyłanie powiadomień HTTP (webhooków) do systemów zewnętrznych.
 * Integruje serwer Minecraft z aplikacjami webowymi (np. Next.js) w celu aktualizacji WWW.
 * Działa asynchronicznie, nie blokując głównego wątku serwera.
 */
class WebhookDispatcher {

    private string $apiUrl;
    private string $secretToken;
    private bool $enabled = false;

    public function __construct(Plugin $plugin) {
        $config = $plugin->getConfig()->getNested("http.webhooks", []);
        // config.yml używa "api-url" (z myślnikiem)
        $this->apiUrl      = (string) ($config["api-url"]      ?? $config["api_url"] ?? "");
        $this->secretToken = (string) ($config["secret-token"] ?? $config["secret_token"] ?? "");
        $this->enabled     = (bool)   ($config["enabled"]      ?? false);
    }

    /**
     * Asynchronicznie wysyła dane (payload) do API Next.js metodą POST.
     *
     * @param string               $eventType Nazwa zdarzenia (np. "match_finished")
     * @param array<string, mixed> $data      Dane do wysłania jako JSON
     */
    public function dispatch(string $eventType, array $data): void {
        if (!$this->enabled || empty($this->apiUrl)) {
            return;
        }

        $payload = json_encode([
            "event"     => $eventType,
            "timestamp" => time(),
            "data"      => $data,
        ]);

        if ($payload === false) {
            return;
        }

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->secretToken,
            "User-Agent: RivarlyPracticeCore/1.0",
        ];

        Server::getInstance()->getAsyncPool()->submitTask(
            new SendWebhookAsyncTask($this->apiUrl, $payload, $headers)
        );
    }
}

/**
 * Zadanie asynchroniczne wykonujące żądanie HTTP w osobnym wątku.
 * Nie blokuje głównego wątku serwera Minecraft.
 */
class SendWebhookAsyncTask extends AsyncTask {

    private string $url;
    private string $payload;
    /** @var string[] */
    private array $headers;

    /**
     * @param string   $url
     * @param string   $payload JSON-encoded body
     * @param string[] $headers
     */
    public function __construct(string $url, string $payload, array $headers) {
        $this->url     = $url;
        $this->payload = $payload;
        $this->headers = $headers;
    }

    public function onRun(): void {
        $ch = curl_init($this->url);
        if ($ch === false) {
            return;
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS,     $this->payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $this->headers);
        curl_setopt($ch, CURLOPT_TIMEOUT,        4);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        curl_exec($ch);
        curl_close($ch);
    }
}
