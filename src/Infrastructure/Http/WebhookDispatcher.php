<?php

declare(strict_types=1);

namespace Infrastructure\Http;

use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use Core\Plugin;

/**
 * TODO: Odpowiada za wysyłanie powiadomień HTTP (webhooków) do systemów zewnętrznych.
 * Integruje serwer Minecraft z aplikacjami webowymi (np. Next.js) w celu aktualizacji WWW.
 * Działa asynchronicznie, przekazując wyniki meczów do zewnętrznych API.
 */
class WebhookDispatcher
{

    private string $apiUrl;
    private string $secretToken;
    private bool $enabled = false;

    /**
     * Konstruktor dyspenczera webhooków.
     * Pobiera konfiguracje z sekcji http.webhooks w config.yml.
     */
    public function __construct(Plugin $plugin)
    {
        $config = $plugin->getConfig()->getNested("http.webhooks", []);
        $this->apiUrl = (string)($config["api_url"] ?? "");
        $this->secretToken = (string)($config["secret_token"] ?? "");
        $this->enabled = (bool)($config["enabled"] ?? false);
    }

    /**
     * Asynchronicznie wysyła dane (payload) do API Next.js za pomocą metody POST.
     * * @param string $eventType Nazwa zdarzenia (np. "match_finished", "rank_update")
     * @param array<string, mixed> $data Dowolne dane, które zostaną zamienione na JSON
     */
    public function dispatch(string $eventType, array $data): void
    {
        if (!$this->enabled || empty($this->apiUrl)) {
            return;
        }

        $payload = [
            "event" => $eventType,
            "timestamp" => time(),
            "data" => $data
        ];

        // Nagłówki HTTP, w tym token autoryzacyjny (Bearer)
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->secretToken,
            "User-Agent: RivarlyPracticeCore/1.0"
        ];

        // Pushujemy zadanie do puli wątków PocketMine (Async Pool)
        Server::getInstance()->getAsyncPool()->submitTask(new SendWebhookAsyncTask($this->apiUrl, json_encode($payload), $headers));
    }
}

    /**
     * Wewnętrza klasa zadania asynchronicznego.
     * Cały kod wewnątrz metody onRun wykonuje się w osobnym wątku, nie obciążając serwera Minecraft!
     */
    class SendWebhookAsyncTask extends AsyncTask {

        private string $url;
        private string $payload;
        /** @var string[] */
        private array $headers;

        /**
         * @param string[] $headers
         */
        public function __construct(string $url, string $payload, array $headers) {
            $this->url = $url;
            $this->payload = $payload;
            $this->headers = $headers;
        }

        public function onRun(): void {
            $ch = curl_init($this->url);
            if ($ch === false) {
                return;
            }

            // Konfiguracja cURL pod bezpieczny i szybki transfer danych POST
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Timeout 4 sekundy
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_PROXY_SSL_VERIFYPEER, true);

            // Wykonujemy zapytanie (w osobnym wątku worker!)
            curl_exec($ch);
            curl_close($ch);
        }
}
