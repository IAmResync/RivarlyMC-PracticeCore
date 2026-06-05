<?php

declare(strict_types=1);

namespace Combat; // DOPASOWANO: Spójny namespace w Twoim projekcie

use pocketmine\player\Player;
use pocketmine\math\Vector3;
use Domain\GameMode\GameModeConfig;
use Config\PluginConfig;

/**
 * Custom knockback engine – nadpisuje domyślny PMMP knockback.
 * Serce feel'u PvP na serwerze. Wszystkie wartości konfigurowalne w config.yml.
 *
 * Wzorzec użycia (w CombatListener po EntityDamageByEntityEvent):
 * $this->knockback->applyKnockback($attacker, $victim, $config);
 */
final class KnockbackEngine {

    private float $baseHorizontal;
    private float $baseVertical;

    public function __construct(PluginConfig $config) {
        // NAPRAWIONO: Metody są już w pełni zaimplementowane w PluginConfig
        $this->baseHorizontal = $config->getKnockbackHorizontal();
        $this->baseVertical   = $config->getKnockbackVertical();
    }

    // -----------------------------------------------------------------------
    // Publiczne API
    // -----------------------------------------------------------------------

    /**
     * Aplikuje knockback na ofiarę w kierunku od atakującego.
     * Respektuje mnożnik z GameModeConfig (np. Sumo ma wyższy knockback).
     */
    public function applyKnockback(Player $attacker, Player $victim, GameModeConfig $modeConfig): void {
        $multiplier = $modeConfig->getKnockbackMultiplier();

        $horizontal = $this->baseHorizontal * $multiplier;
        $vertical   = $this->baseVertical   * $multiplier;

        $vector = $this->calculateKnockbackVector($attacker, $victim, $horizontal, $vertical);

        $currentMotion = $victim->getMotion();

        // Knockback stack: jeśli ofiara już leci, częściowo dodajemy do istniejącego wektora
        // zamiast zerować (płynniejszy feel, zgodny z vanilla)
        $newX = ($currentMotion->x / 2.0) + $vector->x;
        $newY = $vector->y;
        $newZ = ($currentMotion->z / 2.0) + $vector->z;

        // Clamp vertical żeby nie wystrzelić gracza przez sufit (ograniczenie PMMP)
        $newY = min($newY, 0.45);

        $victim->setMotion(new Vector3($newX, $newY, $newZ));
    }

    /**
     * Wersja z ręcznym multiplierem – używana przez Sumo (100% kb) lub specjalne eventy.
     */
    public function applyKnockbackWithMultiplier(Player $attacker, Player $victim, float $multiplier): void {
        $horizontal = $this->baseHorizontal * $multiplier;
        $vertical   = $this->baseVertical   * $multiplier;

        $vector = $this->calculateKnockbackVector($attacker, $victim, $horizontal, $vertical);

        $victim->setMotion(new Vector3($vector->x, $vector->y, $vector->z));
    }

    // -----------------------------------------------------------------------
    // Obliczenia wektora
    // -----------------------------------------------------------------------

    /**
     * Oblicza kierunek knockbacku na podstawie różnicy pozycji.
     * Normalizuje wektor poziomy żeby siła była stała niezależnie od odległości.
     */
    private function calculateKnockbackVector(
        Player $attacker,
        Player $victim,
        float  $horizontal,
        float  $vertical,
    ): Vector3 {
        $aPos = $attacker->getPosition();
        $vPos = $victim->getPosition();

        $dx = $vPos->getX() - $aPos->getX();
        $dz = $vPos->getZ() - $aPos->getZ();

        // Jeśli gracze stoją dokładnie w tym samym miejscu – knockback w kierunku spojrzenia atakującego
        $length = sqrt($dx * $dx + $dz * $dz);
        if ($length < 0.0001) {
            $yaw = $attacker->getLocation()->getYaw();
            $dx  = -sin(deg2rad($yaw));
            $dz  =  cos(deg2rad($yaw));
            $length = 1.0;
        }

        // Znormalizowany wektor poziomy × siła
        $normalizedX = ($dx / $length) * $horizontal;
        $normalizedZ = ($dz / $length) * $horizontal;

        return new Vector3($normalizedX, $vertical, $normalizedZ);
    }
}