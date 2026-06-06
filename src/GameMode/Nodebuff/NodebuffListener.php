<?php

declare(strict_types=1);

namespace GameMode\Nodebuff;

use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\event\entity\ProjectileHitBlockEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileLaunchEvent; // DODANO: Automatyczna rejestracja rzutu
use pocketmine\event\Listener;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\player\Player;

/**
 * Obsługuje logikę zdarzeń specyficzną dla trybu Nodebuff.
 */
final class NodebuffListener implements Listener {

    /**
     * potionEntityId => throwerUuid
     * Trackowane od momentu rzucenia mikstury do jej rozbicia.
     *
     * @var array<int, string>
     */
    private array $potionThrowers = [];

    /**
     * @param NodebuffConfig $config
     * @param callable(string $uuid): bool $isNodebuffPlayer Zwraca true jeśli UUID gracza jest w meczu Nodebuff
     */
    public function __construct(
        private readonly NodebuffConfig $config,
        private readonly mixed          $isNodebuffPlayer,
    ) {}

    // -----------------------------------------------------------------------
    // Automatyczne przechwytywanie rzuconej potki (NAPRAWIONO LUKĘ LOGICZNĄ)
    // -----------------------------------------------------------------------

    /**
     * @priority NORMAL
     */
    public function onProjectileLaunch(ProjectileLaunchEvent $event): void {
        $projectile = $event->getEntity();
        if (!$projectile instanceof SplashPotion) {
            return;
        }

        $thrower = $projectile->getOwningEntity();
        if (!$thrower instanceof Player) {
            return;
        }

        $uuid = $thrower->getUniqueId()->toString();
        if (!($this->isNodebuffPlayer)($uuid)) {
            return;
        }

        // Rejestrujemy potkę w podręcznej pamięci podręcznej
        $this->potionThrowers[$projectile->getId()] = $uuid;
    }

    // -----------------------------------------------------------------------
    // Potion trafia w blok – najczęstszy przypadek (rzut pod nogi)
    // -----------------------------------------------------------------------

    /**
     * @priority HIGH
     */
    public function onPotionHitBlock(ProjectileHitBlockEvent $event): void {
        $projectile1 = $event->getEntity();
        if (!$projectile1 instanceof SplashPotion) return;

        $id = $projectile1->getId();
        $throwerUuid = $this->potionThrowers[$id] ?? null;
        if ($throwerUuid === null) return;

        unset($this->potionThrowers[$id]);

        if (!($this->isNodebuffPlayer)($throwerUuid)) return;

        $thrower = $projectile1->getOwningEntity();
        if (!$thrower instanceof Player) return;

        $this->applyHealToThrower($thrower);

        // W PM5 anulujemy event, aby vanilla nie nakładała losowego leczenia AOE
        $projectile1->flagForDespawn();
    }

    // -----------------------------------------------------------------------
    // Potion trafia bezpośrednio w gracza
    // -----------------------------------------------------------------------

    /**
     * @priority HIGH
     */
    public function onPotionHitEntity(ProjectileHitEntityEvent $event): void {
        $projectile2 = $event->getEntity();
        if (!$projectile2 instanceof SplashPotion) return;

        $id = $projectile2->getId();
        $throwerUuid = $this->potionThrowers[$id] ?? null;
        if ($throwerUuid === null) return;

        unset($this->potionThrowers[$id]);

        if (!($this->isNodebuffPlayer)($throwerUuid)) return;

        $thrower = $projectile2->getOwningEntity();
        if (!$thrower instanceof Player) return;

        // Zawsze lecz rzucającego, a nie cel (istotne dla płynnego potowania)
        $this->applyHealToThrower($thrower);
        $projectile2->flagForDespawn();
    }

    // -----------------------------------------------------------------------
    // Blokowanie negatywnych efektów na tym trybie gry
    // -----------------------------------------------------------------------

    /**
     * @priority NORMAL
     */
    public function onEffectAdd(EntityEffectAddEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;
        if (!($this->isNodebuffPlayer)($entity->getUniqueId()->toString())) return;

        // NAPRAWIONO CRASH PM5: Bezpośrednie sprawdzanie obiektów efektów
        if ($event->getEffect()->getType()->isBad()) {
            $event->cancel();
        }
    }

    // -----------------------------------------------------------------------
    // Pomocnicze
    // -----------------------------------------------------------------------

    private function applyHealToThrower(Player $thrower): void {
        $currentHp = $thrower->getHealth();
        $maxHp     = $thrower->getMaxHealth();

        // Wartość leczenia pobierana z NodebuffConfig (np. 3.5 serca = 7.0 HP)
        $healAmount = $this->config->getHealPerPotion();

        $newHp = min($maxHp, $currentHp + $healAmount);
        $thrower->setHealth($newHp);
    }
}