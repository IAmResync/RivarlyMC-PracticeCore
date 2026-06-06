<?php

declare(strict_types=1);

namespace Presentation\HotBar;

use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

/**
 * TODO: Zarządza itemami w hobarze gracza przebywajacego w lobby serwera.
 * Daje graczowi itemy nawigacyjne: Kolejka (Compass), Profil (Book), Turniej (Trophy).
 * Czyści hotbar przy wejściu do meczu i przywraca go po powrocie do lobby.
 */
class HotBarManager {

    /**
     * Daje graczowi itemy nawigacyjne w lobby.
     */
    public function sendLobbyHotBar(Player $player): void {
        $inventory = $player->getInventory();

        // Czyścimy wszystko przed nadaniem, żeby itemy się nie dublowały.
        $inventory->clearAll();
        $player->getArmorInventory()->clearAll();

        // Kolejka (Compass) - Slot 0.
        $queueItem = VanillaItems::COMPASS()->setCustomName("⚔️ Matchmaking Queue (Right-Click)");
        $inventory->setItem(0, $queueItem);

        // Profil (Book) - Slot 4
        $profileItem = VanillaItems::BOOK()->setCustomName("👤 Your Profile & Stats (Right-Click)");
        $inventory->setItem(4, $profileItem);

        // Turniej (Trophy -> Używam Totemu lub Złota jako ikony pucharu) - Slot 8
        $tournamentName = VanillaItems::TOTEM()->setCustomName("🏆 Tournaments (Right-Click)");
        $inventory->setItem(8, $tournamentName);

        // Ustawiam domyślnie zaznaczony pierwszy slot.
        $inventory->setHeldItemIndex(0);
    }

    /**
     * Czyści hotbar przy wejściu do meczu (wywoływane przez MatchManager).
     */
    public function clearForMatch(Player $player): void {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
    }
}
