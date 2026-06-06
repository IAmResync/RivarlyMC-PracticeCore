<?php

declare(strict_types=1);

namespace Infrastructure\Http;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Core\Plugin;

/**
 * TODO: System wysyłający sformatowane komunikaty bezpośrednio na kanały Discorda.
 * Informuje o startujących turniejach, rekordach ELO i ważnych wydarzeniach.
 * Korzysta z Discord Webhooks do budowania społeczności wokół serwera.
 */
class DiscordNotifier {

    private string $webhookUrl;
    private bool $enabled = false;

    /**
     * Konstruktor pobierający dane z sekcji http.discord w config.yml.
     */
    public function __construct(Plugin $plugin) {
        $config = $plugin->getConfig()->getNested("http.discord", []);

        $this->enabled = (bool)($config["enabled"] ?? false);
        $this->webhookUrl = (string)($config["webhook_url"] ?? "");
    }

    /**
     * Wysyła prostą wiadomość tekstową na Discorda.
     */
    public function sendMessage(string $content): void {
        if (!$this->enabled || empty($this->webhookUrl)) {
            return;
        }

        $this->submit([
            "content" => $content,
        ]);
    }

    /**
     * Wysyła zaawansowaną, sformatowaną ramę (Embed) na Discorda.
     * Idealne pod ogłoszenia o turniejach lub pobitych rekordach ELO.
     * * @param string $title Tytuł wiadomości
     * @param string $description Główna treść
     * @param int $color Kolor paska z boku (w formacie HEX zamienionym na INT, np. 0x00FF00 dla zielonego)
     * @param array<int, array{name: string, value: string, inline?: bool}> $fields Dodatkowe pola informacyjne
     */
    public function sendEmbed(string $title, string $description, int $color = 0x3498db, array $fields = []): void {
        if (!$this->enabled || empty($this->webhookUrl)) {
            return;
        }

        $embed = [
            "title" => $title,
            "description" => $description,
            "color" => $color,
            "timestamp" => date("c")
        ];

        if (!empty($fields)) {
            $embed["fields"] = $fields;
        }

        $this->submit([
            "embed" => [$embed],
        ]);
    }

    /**
     * Przekazuje spakowany payload do pobocznego wątku procesora.
     */
    private function submit(array $payload): void {
        Server::getInstance()->getAsyncPool()->submitTask(
            new SendDiscordWebhookAsyncTask($this->webhookUrl, json_encode($payload))
        );
    }
}

    /**
     * Zadanie asynchroniczne wysyłające dane do API Discorda.
     * Chroni serwer Minecraft przed lagami sieciowymi.
     */
    class SendDiscordWebhookAsyncTask extends AsyncTask {

        private string $url;
        private string $payload;

        public function __construct(string $url, string $payload) {
            $this->url = $url;
            $this->payload = $payload;
        }

        public function onRun(): void {
            $ch = curl_init($this->url);
            if ($ch === false) {
                return;
            }

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "User-Agent: RivarlyPracticeCore/1.0"
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            curl_exec($ch);
            curl_close($ch);
        }
    }
