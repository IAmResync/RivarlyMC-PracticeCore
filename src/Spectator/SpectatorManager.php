<?php

declare(strict_types=1);

namespace Spectator;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * TODO: Zarządza listą graczy obserwujących aktywne mecze.
 * Teleportuje spectatorów na arenę, nadaje efekt niewidzialności i AdventureMode.
 * Usuwa gracza ze spectatorów gdy mecz się kończy lub gracz wychodzi z serwera.
 */
class SpectatorManager {

    /**
     * Przechowuje informację, jaki mecz (ID) ogląda dany gracz: [spectator_uuid => match_id]
     * @var array<string, string>
     */
    private array $spectators = [];

    /**
     * Dodaje gracza jako obserwatora do konkretnego meczu i teleportuje go na arenę.
     */
    public function addSpectator(Player $player, string $matchId, Position $arenaSpawn): void {
        $uuid = $player->getUniqueId()->toString();
        $this->spectators[$uuid] = $matchId;

        // 1. Teleportacja na arenę.
        $player->teleport($arenaSpawn);

        // 2. Ustawienie trybu przygody (AdventureMode)
        $player->setGamemode(GameMode::ADVENTURE);

        // 3. Nadanie efektu niewidzialności (Invisibility) na bardzo długi czas, bez cząsteczek.
        $invisibility = new EffectInstance(
            VanillaEffects::INVISIBILITY(),
            20 * 60 * 60, // Bardzo długi czas trwania (w tickach)
            0,
            false // Ukrycie cząsteczek (particles)
        );
        $player->getEffects()->add($invisibility);

        // Wyłączamy zbieranie i zadawanie obrażeń (opcjonalnie ale myśle że potrzebne zabezpieczenie)
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();

    }

    /**
     * Usuwa gracza z trybu obserwatora i przywraca mu podstawowy stan (usuwa efekty).
     */
    public function removeSpectator(Player $player): void {
        $uuid = $player->getUniqueId()->toString();

        if (!isset($this->spectators[$uuid])) {
            return;
        }

        // Usuwamy efekt niewidzialności.
        $player->getEffects()->remove(VanillaEffects::INVISIBILITY());

        // Przywracamy tryb przetrwania (lub lobby - zostanie obsłużone przez powrót do lobby).
        $player->setGamemode(GameMode::SURVIVAL);
    }

    /**
     * Czyści cała listę spectatorów przypisanych do danego meczu (wywoływane po zakończeniu walki.).
     * @param Player[] $onlinePlayers Lista graczy na serwerze do przefiltrowania i wyczyszczenia.
     */
    public function clearMatchSpectators(string $matchId, array $onlinePlayers): void {
        foreach ($onlinePlayers as $player) {
            $uuid = $player->getUniqueId()->toString();
            if (isset($this->spectators[$uuid]) && $this->spectators[$uuid] === $matchId) {
                $this->removeSpectator($player);

                // Tutaj w przyszłosći śpuszcze gracza z powrotem do lobby za pomocą Teleport/LobbyManager
                $player->sendMessage("");
            }
        }
    }

    /**
     * Sprawdza, czy gracz aktualnie obserwuje jakikolwiek mecz.
     */
    public function isSpectator(Player $player): bool {
        return isset($this->spectators[$player->getUniqueId()->toString()]);
    }

    /**
     * Zwraca ID meczu, który gracz aktualnie obserwuje, lub null.
     */
    public function getObservedMatchId(Player $player): ?string {
        return $this->spectators[$player->getUniqueId()->toString()] ?? null;
    }
}
