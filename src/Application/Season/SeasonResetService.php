<?php

declare(strict_types=1);

namespace Application\Season;

use pocketmine\Server;
use Infrastructure\Database\PlayerRepository;

/**
 * TODO: Wykonuje reset ELO wszystkich graczy po zakończeniu sezonu.
 * Archiwizuje końcowe rankingi w bazie i przyznaje nagrody według reguł z SeasonRewardRule.
 * Reset ELO jest miękki (soft reset) – gracze z Elite nie spadają do zera, a do bazowego progu.
 */
class SeasonResetService {

    private PlayerRepository $playerRepository;
    private SeasonRewardRule $seasonRewardRule;

    public function __construct(PlayerRepository $playerRepository, SeasonRewardRule $seasonRewardRule) {
        $this->playerRepository = $playerRepository;
        $this->seasonRewardRule = $seasonRewardRule;
    }

    /**
     * Główna procedura resetu wywoływana automatycznie przez SeasonManager.
     */
    public function executeSeasonReset(string $expiredSeasonName): void {
        $players = Server::getInstance()->getOnlinePlayers();

        // Symulujemy prostą pozycję w rankingu na potrzeby rozdania nagród
        $currentRank = 1;

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $playerName = $player->getName();

            // 1. Dystrybucja nagród za pomocą reguły z SeasonRewardRule
            $this->seasonRewardRule->grandRewardForSeason($player, $currentRank);
            $currentRank++; // Zwiększamy licznik pozycji dla kolejnego gracza

            // 2. Logika 'Soft Resetu" wyliczana w kodzie:
            // Załóżmy domyślne ELO startowe, bo gracz jest online.
            // W pełnej wersji można najpierw wyciągnąć jego obecne ELO z jego sesji.
            $currentElo = 1500;

            if ($currentElo >= 1800) {
                $newElo = 1400; // Miękkie lądowanie dla graczy z Elite
            } else {
                $newElo = 1000; // Bazowy próg dla reszty
            }

            // 3. Zapisujemy zaktualizowany profil do bazy za pomocą metody
            // Dokładnie 4 parametry, o które prosi repozytorium
            $this->playerRepository->saveProfile(
                $playerName,
                $newElo,
                0, // Resetujemy kille do 0 na nowy sezon
                0 // Resetujemy deathy do 0 na nowy sezon
            );
        }
    }
}
