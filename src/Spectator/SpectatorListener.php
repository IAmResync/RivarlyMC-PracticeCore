<?php

declare(strict_types=1);

namespace Spectator;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;

/**
 * TODO: Blokuje wszelkie interakcje gracza będącego w trybie spectator.
 * Zapobiega niszczeniu bloków, klikaniu itemów i walce z innymi graczami.
 * Pracuje razem z SpectatorManager – najpierw sprawdza stan, potem blokuje event.
 */
class SpectatorListener implements Listener {

    private SpectatorManager $spectatorManager;

    public function __construct(SpectatorManager $spectatorManager) {
        $this->spectatorManager = $spectatorManager;
    }

    /**
     * Blokuje zadawanie obrażeń przez spectatora oraz chroni go przed otrzymywaniem obrażeń
     */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();

        // Jeśli spectator miałby otrzymać jakiekolwiek obrażenie (np. od upadku/magii) - blokujemy
        if ($entity instanceof Player && $this->spectatorManager->isSpectator($entity)) {
            $event->cancel();
            return;
        }

        // Jeśli to walka między bytami (PvP)
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();

            // Blokujemy walkę z innymi graczami, jeśli to atakujący jest obserwatorem
            if ($damager instanceof Player && $this->spectatorManager->isSpectator($damager)) {
                $event->cancel();
            }
        }
    }

    /**
     * Zapobiega niszczeniu bloków przez obserwatora.
     */
    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();

        if ($this->spectatorManager->isSpectator($player)) {
            $event->cancel();
        }
    }

    /**
     * Zapobiega stawianiu bloków przez obserwatora.
     */
    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();

        if ($this->spectatorManager->isSpectator($player)) {
            $event->cancel();
        }
    }

    /**
     * Blokuje klikanie przedmiotów, otwieranie skrzyń i interakcji ze światem.
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();

        if ($this->spectatorManager->isSpectator($player)) {
            $event->cancel();
        }
    }

    /**
     * Zapobiega wyrzuceniu przedmiotów z ekwipunku na ziemię.
     */
    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        $player = $event->getPlayer();

        if ($this->spectatorManager->isSpectator($player)) {
            $event->cancel();
        }
    }

    /**
     * Automatycznie usuwa gracza z pamięci menagera, kiedy ten opuszcza serwer.
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();

        if ($this->spectatorManager->isSpectator($player)) {
            $this->spectatorManager->removeSpectator($player);
        }
    }
}
