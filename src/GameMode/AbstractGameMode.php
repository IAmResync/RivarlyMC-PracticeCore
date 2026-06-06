<?php

declare(strict_types=1);

namespace GameMode;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

/**
 * TODO: Klasa bazowa implementujÄ…ca wspÃ³lne mechaniki dla wszystkich trybÃ³w gry.
 * Zawiera domyÅ›lne zachowania, ktÃ³re mogÄ… byÄ‡ nadpisywane przez konkretne tryby.
 * Upraszcza proces tworzenia nowych trybÃ³w poprzez reuÅ¼ycie kodu bazowego.
 */
abstract class AbstractGameMode {

    protected string $name;
    protected bool $allowBuilding;
    protected bool $allowHunger;

    public function __construct(string $name, bool $allowBuilding = false, bool $allowHunger = true) {
        $this->name = $name;
        $this->allowBuilding = $allowBuilding;
        $this->allowHunger = $allowHunger;
    }

    /**
     * Zwraca unikalnÄ… nazwÄ™ trybu gry (np. "NoDebuff").
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Czy tryb pozwala na stawianie i niszczenie blokÃ³w.
     */
    public function isBuildingAllowed(): bool {
        return $this->allowBuilding;
    }

    /**
     * Czy na tym trybie gracze tracÄ… pasek gÅ‚odu.
     */
    public function isHungerEnabled(): bool {
        return $this->allowHunger;
    }

    /**
     * Hook wywoÅ‚ywany w momencie startu pojedynku na tym trybie.
     * SÅ‚uÅ¼y m.in. do nadawania domyÅ›lnych efektÃ³w mikstur.
     */
    public function onStart(Player $player1, Player $player2): void {
        foreach ([$player1, $player2] as $player) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getEffects()->clear();
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());
        }
    }

    /**
     * Hook wywoÅ‚ywany automatycznie po zakoÅ„czeniu meczu.
     */
    public function onStop(Player $player1, Player $player2): void {
        // Miejsce na logikÄ™ czyszczÄ…cÄ… po walce
    }

    /**
     * DomyÅ›lna obsÅ‚uga obraÅ¼eÅ„ na danym trybie.
     * Pozwala Å‚atwo zmodyfikowaÄ‡ knockback lub redukowaÄ‡ poszczegÃ³lne rodzaje damage.
     */
    public function handleDamage(EntityDamageEvent $event): void {
        // DomyÅ›lne zachowanie PocketMine (tryby mogÄ… to nadpisaÄ‡ np. pod Boxing).
    }

    /**
     * Hook wywoÅ‚ywany, gdy gracz zginie w trakcie trwania tego trybu.
     */
    public function onPlayerDeath(string $matchId, string $killerName, string $victimName): void {
        // DomyÅ›lny komunikat o eliminacji
    }

    /**
     * Helper: parsuje string itema i dodaje enchanty.
     * @param array<string, int> $enchants enchantId => level
     */
    protected function buildItem(string $itemString, array $enchants = [], int $count = 1): Item {
        $item = StringToItemParser::getInstance()->parse($itemString) ?? \pocketmine\item\VanillaItems::AIR();
        $item->setCount($count);
        foreach ($enchants as $enchantId => $level) {
            if (is_int($enchantId)) {
                $enchantment = EnchantmentIdMap::getInstance()->fromId($enchantId);
            } else {
                $enchantment = \pocketmine\item\enchantment\StringToEnchantmentParser::getInstance()->parse((string)$enchantId);
            }
            if ($enchantment !== null) {
                $item->addEnchantment(new EnchantmentInstance($enchantment, (int)$level));
            }
        }
        return $item;
    }
}

