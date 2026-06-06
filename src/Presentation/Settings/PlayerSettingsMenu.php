<?php

declare(strict_types=1);

namespace Presentation\Settings;

use pocketmine\player\Player;
use pocketmine\form\Form;
use Application\Player\SessionManager;
use Domain\Player\PlayerProfile;

/**
 * Interaktywne menu ustawień gracza — otwierane przez item w HotBarze.
 *
 * Ustawienia:
 *   - Toggle: przyjmowanie wyzwań na duel (acceptingDuels)
 *   - Toggle: możliwość spectowania twoich meczów (spectatorEnabled)
 *   - Toggle: scoreboard widoczny / ukryty
 *
 * Używa formularzy MCPE (SimpleForm + ModalForm) z pocketmine/forms.
 *
 * Użycie w HotBarManager:
 *   case 'settings': PlayerSettingsMenu::open($player, $sessionManager, $scoreboardManager);
 *
 * Cały flow jest self-contained — open() tworzy form i wysyła, callback aktualizuje profil.
 */
final class PlayerSettingsMenu {

    // Kolory toogleów
    private const ON  = '§aON §f';
    private const OFF = '§cOFF §f';

    /**
     * Otwiera główne menu ustawień dla gracza.
     */
    public static function open(
        Player         $player,
        SessionManager $sessionManager,
    ): void {
        $uuid    = $player->getUniqueId()->toString();
        $profile = $sessionManager->getSessionByUuid($uuid);

        if ($profile === null) {
            $player->sendMessage('§cProfil is not loaded yet. Wait a moment.');
            return;
        }

        $duelsLabel      = ($profile->isAcceptingDuels()     ? self::ON : self::OFF) . 'Accept duels';
        $spectatorLabel  = ($profile->isSpectatorEnabled()   ? self::ON : self::OFF) . 'Allow spectators';

        $form = new class($profile, $sessionManager, $player) implements Form {

            public function __construct(
                private readonly PlayerProfile $profile,
                private readonly SessionManager $sessionManager,
                private readonly Player $player,
            ) {}

            public function jsonSerialize(): mixed {
                $duelsLabel     = ($this->profile->isAcceptingDuels()   ? '§aON §f' : '§cOFF §f') . 'Accept duels';
                $spectatorLabel = ($this->profile->isSpectatorEnabled() ? '§aON §f' : '§cOFF §f') . 'Allow spectators';

                return [
                    'type'    => 'form',
                    'title'   => '§b⚙ Ustawienia',
                    'content' => '§7Kliknij aby przełączyć:',
                    'buttons' => [
                        ['text' => $duelsLabel],
                        ['text' => $spectatorLabel],
                        ['text' => '§7Zamknij'],
                    ],
                ];
            }

            public function handleResponse(Player $player, mixed $data): void {
                if ($data === null) return; // zamknięto bez klikania

                $profile = $this->sessionManager->getSessionByUuid($player->getUniqueId()->toString());
                if ($profile === null) return;

                match ((int) $data) {
                    0 => self::toggleDuels($player, $profile),
                    1 => self::toggleSpectator($player, $profile),
                    default => null,
                };

                // Ponownie otwórz menu żeby gracz widział zaktualizowany stan
                if ((int) $data !== 2) {
                    PlayerSettingsMenu::open($player, $this->sessionManager);
                }
            }

            private static function toggleDuels(Player $player, PlayerProfile $profile): void {
                $newValue = !$profile->isAcceptingDuels();
                $profile->setAcceptingDuels($newValue);
                $status = $newValue ? '§aON' : '§cOFF';
                $player->sendMessage("§fAccept duels: §9{$status}");
            }

            private static function toggleSpectator(Player $player, PlayerProfile $profile): void {
                $newValue = !$profile->isSpectatorEnabled();
                $profile->setSpectatorEnabled($newValue);
                $status = $newValue ? '§aON' : '§cOFF';
                $player->sendMessage("§fAllow spectators: §9{$status}");
            }
        };

        $player->sendForm($form);
    }
}
