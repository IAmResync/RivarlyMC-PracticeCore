<?php

declare(strict_types=1);

namespace Presentation\Profile;

use pocketmine\player\Player;
use pocketmine\form\Form;
use Application\Player\SessionManager;
use Domain\Player\PlayerProfile;
use Presentation\Settings\PlayerSettingsMenu;

/**
 * Główny ekran profilu gracza — otwierany przez kliknięcie Bookiem w hotbarze.
 *
 * Pokazuje: nazwę, ELO, dywizję, W/L, KDR, win rate.
 * Przyciski: Statystyki szczegółowe | Ustawienia | Zamknij
 */
final class ProfileForm {

    public static function open(Player $player, SessionManager $sessionManager): void {
        $uuid    = $player->getUniqueId()->toString();
        $profile = $sessionManager->getSessionByUuid($uuid);

        if ($profile === null) {
            $player->sendMessage('§cProfile is not loaded yet. Wait a moment and try again.');
            return;
        }

        $kdr      = $profile->getKdr();
        $winRate  = $profile->getWinRate();
        $division = $profile->getDivision();
        $elo      = $profile->getGlobalElo();
        $wins     = $profile->getGlobalWins();
        $losses   = $profile->getGlobalLosses();
        $kills    = $profile->getGlobalKills();
        $deaths   = $profile->getGlobalDeaths();
        $matches  = $profile->getTotalMatchesPlayed();

        $divisionColor = self::getDivisionColor($division);

        $content = implode("\n", [
        "PLAYER={$player->getName()}",
        "DIVISION={$division}",
        "ELO={$elo}",
        "WINS={$wins}",
        "LOSSES={$losses}",
        "MATCHES={$matches}",
        "KILLS={$kills}",
        "DEATHS={$deaths}",
        "KDR={$kdr}",
        "WINRATE={$winRate}"
        ]);

        $form = new class($profile, $sessionManager, $content) implements Form {

            public function __construct(
                private readonly PlayerProfile  $profile,
                private readonly SessionManager $sessionManager,
                private readonly string         $content,
            ) {}

            public function jsonSerialize(): mixed {
                return [
                    'type'    => 'form',
                    'title'   => 'Your Profile',
                    'content' => $this->content,
                    'buttons' => [
                        ['text' => 'Detailed Statistics'],
                        ['text' => 'Settings'],
                        ['text' => 'Close'],
                    ],
                ];
            }

            public function handleResponse(Player $player, mixed $data): void {
                if ($data === null) return;

                match ((int) $data) {
                    0 => StatsForm::open($player, $this->sessionManager),
                    1 => PlayerSettingsMenu::open($player, $this->sessionManager),
                    default => null,
                };
            }
        };

        $player->sendForm($form);
    }

    private static function getDivisionColor(string $division): string {
        return match (strtolower($division)) {
            'iron'        => '§7',
            'bronze'      => '§6',
            'silver'      => '§f',
            'gold'        => '§e',
            'platinum'    => '§b',
            'diamond'     => '§3',
            'master'      => '§d',
            'grandmaster' => '§c',
            default       => '§f',
        };
    }
}
