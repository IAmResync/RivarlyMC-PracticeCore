<?php

declare(strict_types=1);

namespace Application\Player;

use Domain\Stats\CombatSession;
use pocketmine\player\Player;

/**
 * Autorski system przetwarzania mikro-zdarzeń PvP na serwerze NoMercy.
 * Most łączący surowe akcje z silnika PocketMine z domeną statystyk meczowych.
 */
final class StatsCollector {

    /** @var array<string, CombatSession> playerUuid => aktywna sesja statystyk */
    private array $activeSessions = [];

    public function __construct() {
        // Konstruktor przygotowany pod ewentualne wstrzykiwanie loggów / managerów
    }

    /**
     * Rejestruje nową, żywą sesję śledzenia statystyk dla gracza wchodzącego na arenę.
     */
    public function startSession(Player $player): void {
        $uuid = $player->getUniqueId()->toString();
        $this->activeSessions[$uuid] = new CombatSession($uuid, $player->getName());
    }

    /**
     * Kończy sesję zbierania danych i zwraca pełny snapshot walki gotowy do zapisu SQL.
     */
    public function endSession(Player $player): ?\Rivarly\Domain\Stats\MatchSnapshot {
        $uuid = $player->getUniqueId()->toString();
        $session = $this->activeSessions[$uuid] ?? null;

        if ($session === null) {
            return null;
        }

        unset($this->activeSessions[$uuid]);
        return $session->toSnapshot();
    }

    /**
     * Pobiera aktywną sesję gracza (przydatne np. do weryfikacji przez AntiCheat).
     */
    public function getSession(Player $player): ?CombatSession {
        return $this->activeSessions[$player->getUniqueId()->toString()] ?? null;
    }

    // -----------------------------------------------------------------------
    // Przechwytywanie i delegowanie mikro-zdarzeń z eventów PocketMine
    // -----------------------------------------------------------------------

    /**
     * Wywołaj w AnimatePacket / PlayerAnimationEvent (machnięcie ręką/mieczem).
     */
    public function handleSwing(Player $player): void {
        $session = $this->getSession($player);
        if ($session !== null) {
            $session->recordSwing();
        }
    }

    /**
     * Wywołaj w EntityDamageByEntityEvent, gdy atakującym (Damager) jest gracz.
     */
    public function handleHit(Player $attacker, bool $isCritical = false): void {
        $session = $this->getSession($attacker);
        if ($session !== null) {
            $session->recordHit($isCritical);
        }
    }

    /**
     * Wywołaj w PlayerMissAttackEvent lub własnym trackerze chybionych ciosów.
     */
    public function handleMiss(Player $player): void {
        $session = $this->getSession($player);
        if ($session !== null) {
            $session->recordMiss();
        }
    }

    /**
     * Wywołaj w PlayerItemConsumeEvent, gdy gracz pije miksturę lub rzuca Splash Potion.
     */
    public function handlePotionUsed(Player $player): void {
        $session = $this->getSession($player);
        if ($session !== null) {
            $session->recordPotionUsed();
        }
    }

    /**
     * Wywołaj w PlayerItemConsumeEvent, gdy gracz zjada złotą (lub kox) jabłko.
     */
    public function handleGoldenAppleEaten(Player $player): void {
        $session = $this->getSession($player);
        if ($session !== null) {
            $session->recordGoldenAppleEaten();
        }
    }

    /**
     * Wywołaj w ProjectileLaunchEvent, gdy rzucającym perłę (EnderPearl) jest gracz.
     */
    public function handleEnderPearlThrown(Player $player): void {
        $session = $this->getSession($player);
        if ($session !== null) {
            $session->recordEnderPearlThrown();
        }
    }

    /**
     * Wywołaj w EntityDamageByEntityEvent w sekcji naliczania finalnego damage.
     * Przetwarza obrażenia zadane (attacker) i otrzymane (victim) za jednym zamachem,
     * automatycznie zrywając combo ofiary!
     */
    public function handleDamageExchange(Player $attacker, Player $victim, int $damageAmount): void {
        $attackerSession = $this->getSession($attacker);
        if ($attackerSession !== null) {
            $attackerSession->recordDamageDealt($damageAmount);
        }

        $victimSession = $this->getSession($victim);
        if ($victimSession !== null) {
            $victimSession->recordDamageTaken($damageAmount);
        }
    }

    /**
     * Wywołaj w PlayerDeathEvent (gracz zabija drugiego gracza).
     */
    public function handleKillDeathExchange(Player $killer, Player $victim): void {
        $killerSession = $this->getSession($killer);
        if ($killerSession !== null) {
            $killerSession->recordKill();
        }

        $victimSession = $this->getSession($victim);
        if ($victimSession !== null) {
            $victimSession->recordDeath();
        }
    }
}