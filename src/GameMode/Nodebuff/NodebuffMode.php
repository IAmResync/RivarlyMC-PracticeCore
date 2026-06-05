<?php

declare(strict_types=1);

namespace GameMode\Nodebuff;

use GameMode\AbstractGameMode;
use Domain\GameMode\GameModeInterface;
use Domain\GameMode\GameModeConfig;
use pocketmine\player\Player; // DODANO: Wymagane do metod start/end
use pocketmine\item\VanillaItems;
use pocketmine\item\PotionType;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;

/**
 * Implementacja konkretnego trybu gry typu Nodebuff.
 * Definiuje specyficzne zasady walki, gdzie kluczowe jest używanie mikstur regeneracji.
 * Odpowiada za wczytanie odpowiedniego kit-u startowego dla każdego gracza w meczu.
 */
final class NodebuffMode extends AbstractGameMode implements GameModeInterface {

    /**
     * Zwraca unikalny identyfikator trybu.
     */
    public function getName(): string {
        return 'nodebuff';
    }

    /**
     * Zwraca ładną nazwę wyświetlaną w menu/na scoreboardzie.
     */
    public function getDisplayName(): string {
        return '§bNodebuff';
    }

    /**
     * Zwraca konfigurację trybu gry.
     */
    public function getConfig(): GameModeConfig {
        return new GameModeConfig();
    }

    /**
     * Wywoływane w momencie rozpoczęcia pojedynku na tym trybie.
     * (NAPRAWIONO: Spełnia kontrakt z GameModeInterface)
     */
    public function onMatchStart(Player|string $matchId): void {
        $matchId->sendMessage("§aPojedynek Nodebuff się rozpoczął! Powodzenia.");
    }

    /**
     * Wywoływane w momencie zakończenia pojedynku na tym trybie.
     * (NAPRAWIONO: Spełnia kontrakt z GameModeInterface)
     */
    public function onMatchEnd(Player|string $matchId): void {
        // Czyszczenie gracza po walce (efekty zostaną usunięte automatycznie przez MatchManagera)
        $matchId->sendMessage("§cKoniec walki!");
    }

    /**
     * Zwraca strukturę przedmiotów (kitu) w formacie czystego PHP.
     * @return array<int, array<string, mixed>>
     */
    public function getInventoryTemplate(): array {
        $items = [];

        // Slot 0: Diamentowy miecz z enchantami
        $items[0] = [
            'id' => 'minecraft:diamond_sword',
            'count' => 1,
            'enchantments' => [
                'sharpness' => 2,
                'fire_aspect' => 1
            ]
        ];

        // Slot 7: Perły enderu
        $items[7] = [
            'id' => 'minecraft:ender_pearl',
            'count' => 16
        ];

        // Slot 8: Złote jabłko
        $items[8] = [
            'id' => 'minecraft:golden_apple',
            'count' => 1
        ];

        // Reszta slotów wypełniona rzucanymi potkami uzdrawiania II
        for ($i = 1; $i <= 35; $i++) {
            if (isset($items[$i])) {
                continue;
            }
            $items[$i] = [
                'id' => 'minecraft:splash_potion',
                'count' => 1,
                'potion_type' => 'strong_healing'
            ];
        }

        return $items;
    }

    /**
     * Zwraca strukturę zbroi w formacie czystego PHP.
     * @return array<int, array<string, mixed>>
     */
    public function getArmorTemplate(): array {
        return [
            0 => ['id' => 'minecraft:diamond_helmet', 'count' => 1],
            1 => ['id' => 'minecraft:diamond_chestplate', 'count' => 1],
            2 => ['id' => 'minecraft:diamond_leggings', 'count' => 1],
            3 => ['id' => 'minecraft:diamond_boots', 'count' => 1]
        ];
    }

    /**
     * Zwraca stałe efekty w formacie [nazwa => poziom].
     * @return array<string, int>
     */
    public function getMatchLongEffects(): array {
        return [
            'speed' => 2
        ];
    }
}