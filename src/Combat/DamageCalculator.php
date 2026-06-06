<?php

declare(strict_types=1);

namespace Combat;

use pocketmine\player\Player;
use pocketmine\item\Armor;
use pocketmine\entity\effect\VanillaEffects;
use Domain\GameMode\GameModeConfig;

/**
 * Kalkuluje finalne obrażenia po uwzględnieniu mechanik PvP serwera.
 * W pełni kompatybilne z PocketMine-MP v5.
 */
final class DamageCalculator {

    // -----------------------------------------------------------------------
    // Publiczne API
    // -----------------------------------------------------------------------

    /**
     * Oblicza finalne obrażenia.
     *
     * @param float           $baseDamage Bazowe obrażenia z ItemStack/broni
     * @param GameModeConfig  $config     Konfiguracja trybu gry
     */
    public function calculate(
        Player         $attacker,
        Player         $victim,
        float          $baseDamage,
        GameModeConfig $config,
    ): DamageResult {
        $damage     = $baseDamage;
        $isCritical = false;

        // 1. Critical hit – gracz spada (motionY < -0.08 i brak kontaktu z ziemią)
        $motion = $attacker->getMotion();
        if ($motion->y < -0.08 && !$attacker->isOnGround()) {
            $damage    *= 1.5;
            $isCritical = true;
        }

        // 2. Efekt Strength (każdy level = +3 damage)
        $effects = $attacker->getEffects();
        $strength = $effects->get(VanillaEffects::STRENGTH());
        if ($strength !== null) {
            // NAPRAWIONO PM5: getAmplifier() zwraca 0 dla Strength I, stąd (+1)
            $level = $strength->getAmplifier() + 1;
            $damage += 3.0 * $level;
        }

        // 3. Efekt Weakness (każdy level = -4 damage, min 0)
        $weakness = $effects->get(VanillaEffects::WEAKNESS());
        if ($weakness !== null) {
            // NAPRAWIONO PM5: getAmplifier() + 1
            $level = $weakness->getAmplifier() + 1;
            $damage = max(0.0, $damage - (4.0 * $level));
        }

        // 4. Armor reduction ofiary (PM5 API)
        $armorReduction   = $this->calculateArmorReduction($victim);
        $damageAfterArmor = $damage * (1.0 - $armorReduction);

        // 5. Efekt Resistance ofiary (każdy level = -20% po armorze)
        $resistance = $victim->getEffects()->get(VanillaEffects::RESISTANCE());
        $wasBlocked = false;
        if ($resistance !== null) {
            // NAPRAWIONO PM5: getAmplifier() + 1
            $level = $resistance->getAmplifier() + 1;
            $resistanceFactor = 1.0 - (0.20 * $level);

            $damageAfterArmor = max(0.0, $damageAfterArmor * $resistanceFactor);
            $wasBlocked = $level >= 5; // pełny blok obrażeń przy Resistance V
        }

        // Modyfikator specyficzny dla trybu gry z GameModeConfig (np. redukcja lub podbicie dmg)
        // Jeśli GameModeConfig ma metodę getDamageMultiplier(), możesz ją tu wpiąć:
        // $damageAfterArmor *= $config->getDamageMultiplier();

        $finalDamage = round(max(0.0, $damageAfterArmor), 2);

        return new DamageResult(
            finalDamage:    $finalDamage,
            rawDamage:      $damage,
            armorReduction: $armorReduction,
            isCritical:     $isCritical,
            wasBlocked:     $wasBlocked,
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Oblicza % obrażeń pochłoniętych przez zbroję (0.0 – 0.8).
     * Każdy punkt pancerza = 4% redukcja, maksymalnie 80% (Vanilla cap).
     */
    private function calculateArmorReduction(Player $victim): float {
        $totalPoints = 0;

        // NAPRAWIONO PM5: Pobieranie bezpośrednio zawartości ekwipunku zbroi
        foreach ($victim->getArmorInventory()->getContents() as $item) {
            if ($item instanceof Armor) {
                $totalPoints += $item->getDefensePoints();
            }
        }

        return min(0.80, $totalPoints * 0.04);
    }
}

// ---------------------------------------------------------------------------
// Value object wyniku kalkulacji obrażeń
// ---------------------------------------------------------------------------

final class DamageResult {

    public function __construct(
        public readonly float $finalDamage,
        public readonly float $rawDamage,
        public readonly float $armorReduction,
        public readonly bool  $isCritical,
        public readonly bool  $wasBlocked,
    ) {}

    public function getFormattedDamage(): string {
        return number_format($this->finalDamage, 1);
    }

    /** Ile % obrażeń zostało pochłoniętych przez zbroję */
    public function getReductionPercent(): int {
        return (int) round($this->armorReduction * 100);
    }
}