<?php

declare(strict_types=1);

namespace AntiCheat;

use pocketmine\Server;
use Core\Plugin;
use Infrastructure\Http\DiscordNotifier;

/**
 * TODO: Zapisuje flagi antycheat-u do bazy danych i loguje je do pliku.
 * Każda flaga zawiera: UUID gracza, typ naruszenia, wartość, timestamp i kontekst meczu.
 * Przy 10+ flagach tego samego typu w ciągu godziny wysyła alert do admina (Discord/log).
 */
class FlagLogger {

    private Plugin $plugin;
    private DiscordNotifier $discordNotifier;
    private string $logFilePath;

    /**
     * Pamięć podręczna do sprawdzania limitów: [$playerUuid][$violationType][] = $timestamp
     * @var array<string, array<string, array<int , int>>>
     */
    private array $recentFlagsCache = [];

    public function __construct(Plugin $plugin, DiscordNotifier $discordNotifier) {
        $this->plugin = $plugin;
        $this->discordNotifier = $discordNotifier;

        // Tworze plik logów w folderze danych pluginu: plugin_data/RivarlyMC-PracticeCore/cheat_flags.log
        $this->logFilePath = $plugin->getDataFolder() . "cheat_flags.log";
    }

    /**
     * Glówna metoda logująca wykrycie podejrzanego zachowania przez AntyCheat.
     */
    public function logFlag(
        string $playerUuid,
        string $playerName,
        string $violationType,
        float $value,
        string $matchContext = "N/A"
    ): void {
        $timestamp = time();
        $dateFormatted = date("Y-m-d H:i:s", $timestamp);

        // 1. Zapis do pliku tekstowego (.log)
        $logMessage = sprintf(
            "[%s] [FLAG] Gracz: %s (%s) | Typ: %s | Wartość: .2f | Mecz: %s\n",
            $dateFormatted,
            $playerName,
            $playerUuid,
            $violationType,
            $value,
            $matchContext
        );
        file_put_contents($this->logFilePath, $logMessage, FILE_APPEND);

        // 2. Sprawdzanie reguły 10+ flag w ciągu godziny (3600 sekund)
        $this->checkAlertThreshold($playerUuid, $playerName, $violationType, $timestamp);
    }

    /**
     * Analizuje częstotliwość flagowania gracza i ewentualnie triggeruje alerty dla administracji.
     */
    private function checkAlertThreshold(string $playerUuid, string $playerName, string $violationType, int $currentTimestamp): void {
        // Czyszczenie starych wpisów (starszych niż godzina) dla zachowania czystości pamięci.
        if (isset($this->recentFlagsCache[$playerUuid][$violationType])) {
            foreach ($this->recentFlagsCache[$playerUuid][$violationType] as $index => $flagTimestamp) {
                if ($currentTimestamp - $flagTimestamp > 3600) {
                    unset($this->recentFlagsCache[$playerUuid][$violationType][$index]);
                }
            }

            // Resetujemy indeksy tablicy
            $this->recentFlagsCache[$playerUuid][$violationType] = array_values($this->recentFlagsCache[$playerUuid][$violationType]);

        }

        // Dodajemy obecną flagę do pamięci podręcznej
        $this->recentFlagsCache[$playerUuid][$violationType][] = $currentTimestamp;

        // Liczymy flagi z ostatniej godziny
        $flagCountInLastHour = count($this->recentFlagsCache[$playerUuid][$violationType]);

        // Jeśli pęknie próg 10 flag...
        if ($flagCountInLastHour >= 10) {
            $this->sendAdminAlert($playerName, $violationType, $flagCountInLastHour);

            // Czyścimy cache dla tej konkretnej flagi, aby nie spamować alertem przy każdej kolejnej flagii (11, 12, 13 ...)
            unset($this->recentFlagsCache[$playerUuid][$violationType]);
        }
    }

    /**
     * Wysyła powiadomienia do adminów na serwerze oraz przygotowuje grunt pod Webhook Discorda.
     */
    private function sendAdminAlert(string $playerName, string $violationType, int $count): void {
        $alertText = "[AntyCheat] Gracz {$playerName} zflagował typ {$violationType} aż {$count} razy w ciągu ostatniej godziny!";

        // Log do konsoli.
        $this->plugin->getLogger()->warning($alertText);

        // Alert do adminów online na serwerze
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->hasPermission("rivarly.antycheat.alerts")) {
                $player->sendMessage($alertText);
            }
        }

        // Miejsce na integrację z Discord Webhook (będzię idealnie pasować do Fazy 2/3 infrastuktury)
          $fields = [
              [
                  "name" => "Gracz",
                  "value" => $playerName,
                  "inline" => true
              ],
              [
                  "name" => "Typ naruszenia",
                  "value" => $violationType,
                  "inline" => true
              ],
              [
                  "name" => "Liczba flag (1h)",
                  "value" => (string) $count,
                  "inline" => true
              ],
              "timestamp" => date("c") // Standarowy format czasu ISO 8601 akceptowany przez Discord.
          ];

        $this->discordNotifier->sendEmbed(
            "WYKRYTO PODEJRZANE ZACHOWANIE",
            "System AntyCheat wykrył powtarzające się anomalię u gracza",
            0xff0000, // Czysty czerwony kolor paska w formacie HEX zmienionym na INT
            $fields
        );
    }
}
