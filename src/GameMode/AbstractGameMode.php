<?php

declare(strict_types=1);

namespace GameMode;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

/**
 * TODO: Klasa bazowa implementująca wspólne mechaniki dla wszystkich trybów gry.
 * Zawiera domyślne zachowania, które mogą być nadpisywane przez konkretne tryby.
 * Upraszcza proces tworzenia nowych trybów poprzez reużycie kodu bazowego.
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
     * Zwraca unikalną nazwę trybu gry (np. "NoDebuff").
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Czy tryb pozwala na stawianie i niszczenie bloków.
     */
    public function isBuildingAllowed(): bool {
        return $this->allowBuilding;
    }

    /**
     * Czy na tym trybie gracze tracą pasek głodu.
     */
    public function isHungerEnabled(): bool {
        return $this->allowHunger;
    }

    /**
     * Hook wywoływany w momencie startu pojedynku na tym trybie.
     * Służy m.in. do nadawania domyślnych efektów mikstur.
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
     * Hook wywoływany automatycznie po zakończeniu meczu.
     */
    public function onStop(Player $player1, Player $player2): void {
        // Miejsce na logikę czyszczącą po walce
    }

    /**
     * Domyślna obsługa obrażeń na danym trybie.
     * Pozwala łatwo zmodyfikować knockback lub redukować poszczególne rodzaje damage.
     */
    public function handleDamage(EntityDamageEvent $event): void {
        // Domyślne zachowanie PocketMine (tryby mogą to nadpisać np. pod Boxing).
    }

    /**
     * Hook wywoływany, gdy gracz zginie w trakcie trwania tego trybu.
     */
    public function onPlayerDeath(string $matchId, string $killerName, string $victimName): void {
        // Domyślny komunikat o eliminacji
    }
}
