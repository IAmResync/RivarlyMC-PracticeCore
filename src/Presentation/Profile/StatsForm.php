<?php

declare(strict_types=1);

namespace Presentation\Profile;

use pocketmine\player\Player;
use pocketmine\form\Form;
use Application\Player\SessionManager;
use Domain\Player\PlayerProfile;

/**
 * Formularz ze szczegółowymi statystykami gracza.
 *
 * Pokazuje globalne statystyki + rozbicie per tryb gry (Nodebuff, Sumo, Boxing, Soup...).
 * Otwierany z ProfileForm po kliknięciu "Statystyki szczegółowe".
 */
final class StatsForm {

    public static function open(Player $player, SessionManager $sessionManager): void {
        $uuid    = $player->getUniqueId()->toString();
        $profile = $sessionManager->getSessionByUuid($uuid);

        if ($profile === null) {
            $player->sendMessage('§c✘ §fBrak danych profilu.');
            return;
        }

        $content = self::buildContent($profile);

        $form = new class($profile, $sessionManager, $content) implements Form {

            public function __construct(
                private readonly PlayerProfile  $profile,
                private readonly SessionManager $sessionManager,
                private readonly string         $content,
            ) {}

            public function jsonSerialize(): mixed {
                return [
                    'type'    => 'form',
                    'title'   => '§e📊 Statystyki',
                    'content' => $this->content,
                    'buttons' => [
                        ['text' => "§b← Wróć do Profilu"],
                        ['text' => "§7✖ Zamknij"],
                    ],
                ];
            }

            public function handleResponse(Player $player, mixed $data): void {
                if ($data === null) return;

                if ((int) $data === 0) {
                    ProfileForm::open($player, $this->sessionManager);
                }
            }
        };

        $player->sendForm($form);
    }

    private static function buildContent(PlayerProfile $profile): string {
        $wins        = $profile->getGlobalWins();
        $losses      = $profile->getGlobalLosses();
        $kills       = $profile->getGlobalKills();
        $deaths      = $profile->getGlobalDeaths();
        $kdr         = $profile->getKdr();
        $winRate     = $profile->getWinRate();
        $matches     = $profile->getTotalMatchesPlayed();
        $bestWS      = $profile->getBestWinStreak();
        $bestKS      = $profile->getBestKillStreak();
        $longestSecs = $profile->getLongestMatchSeconds();
        $longestFmt  = self::formatSeconds($longestSecs);

        $c  = "§e§l▶ GLOBALNE\n§r";
        $c .= "§7━━━━━━━━━━━━━━━━━━━━\n";
        $c .= "§f  Mecze: §f{$matches}   Win Rate: §e{$winRate}%\n";
        $c .= "  §aWygrane: {$wins}   §cPrzegrane: {$losses}\n";
        $c .= "  Kills: §a{$kills}   Deaths: §c{$deaths}   KDR: §e{$kdr}\n";
        $c .= "  Najdł. seria W: §a{$bestWS}   Najdł. seria K: §a{$bestKS}\n";
        $c .= "  Najdłuższy mecz: §f{$longestFmt}\n";

        $modeStats = $profile->getAllModeStats();

        if (count($modeStats) > 0) {
            $c .= "\n§b§l▶ PER TRYB\n§r";
            foreach ($modeStats as $mode => $stats) {
                $mW  = $stats->getWins();
                $mL  = $stats->getLosses();
                $mK  = $stats->getKills();
                $mD  = $stats->getDeaths();
                $mKD = ($mD > 0) ? round($mK / $mD, 2) : (float) $mK;

                $modeName = strtoupper($mode);
                $c .= "§7━━━━━━━━━━━━━━━━━━━━\n";
                $c .= "§f  §b{$modeName}\n";
                $c .= "  §aW: {$mW} §c L: {$mL}   §aK: {$mK} §cD: {$mD}   KDR: §e{$mKD}\n";
            }
        } else {
            $c .= "\n§7  Brak statystyk per tryb — zagraj swój pierwszy mecz!\n";
        }

        $c .= "§7━━━━━━━━━━━━━━━━━━━━";
        return $c;
    }

    private static function formatSeconds(int $seconds): string {
        if ($seconds < 60) return "{$seconds}s";
        $m = (int) floor($seconds / 60);
        $s = $seconds % 60;
        return "{$m}m {$s}s";
    }
}
