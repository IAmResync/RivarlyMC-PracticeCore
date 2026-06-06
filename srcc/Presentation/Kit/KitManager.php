<?php

declare(strict_types=1);

namespace Presentation\Kit;

use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Potion;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\item\PotionTypeRegistry;

/**
 * TODO: Aplikuje KitDefinition na konkretnym graczu w świecie PocketMine.
 * Czyści inwentarz, zbroję i efekty przed wydaniem kitu, żeby nie było remainderów.
 * Jedyne miejsce w projekcie które dotyka Player->getInventory() w kontekście kitu.
 */
final class KitManager {

    /**
     * Główna metoda aplikująca cały zestaw na graczu.
     */
    public function applyKit(Player $player, KitDefinition $kit): void {
        // 1. Czyszczenie gracza ze wszystkiego, żeby uniknąć pozostałości (remainderów)
        $this->clearPlayer($player);

        // 2. Wypełnianie zwykłego ekwipunku slot po slocie
        $this->applyInventory($player, $kit->inventorySlots);

        // 3. Wypełnianie slotów zbroi
        $this->applyArmor($player, $kit->armorSlots);

        // 4. Nakładanie stałych efektów trybu gry
        $this->applyEffects($player, $kit->effects);
    }

    /**
     * Czyści ekwipunek, zbroję, offhand, efekty oraz odnawia HP i głód.
     */
    private function clearPlayer(Player $player): void {
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getOffHandInventory()->clearAll();
        $player->getEffects()->clear();

        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20.0);
    }

    /**
     * Ustawia przedmioty w inwentarzu na konkretnych slotach.
     * @param array<int, array<string, mixed>> $slots
     */
    private function applyInventory(Player $player, array $slots): void {
        $inventory = $player->getInventory();

        foreach ($slots as $slot => $data) {
            $item = $this->buildItem($data);
            if ($item !== null) {
                $inventory->setItem($slot, $item);
            }
        }
    }

    /**
     * Zakłada zbroję na gracza na odpowiednie sloty.
     * @param array<int, array<string, mixed>> $slots
     */
    private function applyArmor(Player $player, array $slots): void {
        $armorInventory = $player->getArmorInventory();

        foreach ($slots as $slot => $data) {
            $item = $this->buildItem($data);
            if ($item === null) {
                continue;
            }

            match($slot) {
                0 => $armorInventory->setHelmet($item),
                1 => $armorInventory->setChestplate($item),
                2 => $armorInventory->setLeggings($item),
                3 => $armorInventory->setBoots($item),
                default => null
            };
        }
    }

    /**
     * Parsuje i nakłada nieskończone efekty potek.
     * @param array<string, int> $effects
     */
    private function applyEffects(Player $player, array $effects): void {
        $effectsManager = $player->getEffects();

        foreach ($effects as $name => $level) {
            $effectType = StringToEffectParser::getInstance()->parse($name);
            if ($effectType === null) {
                continue;
            }

            // W PM5 amplifier zaczyna się od 0 (0 = Poziom I, 1 = Poziom II)
            $amplifier = max(0, $level - 1);

            $effectsManager->add(new EffectInstance(
                effectType: $effectType,
                duration: 999, // Stały efekt na cały mecz
                amplifier: $amplifier,
                visible: false,        // Wyłączone cząsteczki (brak spamu na ekranie)
                ambient: true
            ));
        }
    }

    /**
     * Fabryka budująca pełne przedmioty PocketMine z tablicy opisowej czystego PHP.
     * (NAPRAWIONO API PARSOWANIA MIKSTUR DLA PM5)
     */
    private function buildItem(array $data): ?Item {
        $id = $data['id'] ?? null;
        if ($id === null) {
            return null;
        }

        $item = StringToItemParser::getInstance()->parse((string)$id);
        if ($item === null) {
            return null;
        }

        // Ilość przedmiotów
        $item->setCount((int)($data['count'] ?? 1));

        // Nazwa własna (jeśli podano)
        if (isset($data['custom_name'])) {
            $item->setCustomName((string)$data['custom_name']);
        }

        // Obsługa mikstur w standardzie PocketMine-MP v5
        if (isset($data['potion_type']) && $item instanceof Potion) {
            $potionType = PotionTypeRegistry::getInstance()->register()->get((string)$data['potion_type']);
            if ($potionType !== null) {
                $item->setType($potionType);
            }
        }

        // Nakładanie zaklęć (Enchantments)
        foreach (($data['enchantments'] ?? []) as $enchantName => $enchantLevel) {
            $enchantment = StringToEnchantmentParser::getInstance()->parse((string)$enchantName);
            if ($enchantment !== null) {
                $item->addEnchantment(new EnchantmentInstance($enchantment, (int)$enchantLevel));
            }
        }

        return $item;
    }
}