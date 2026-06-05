<?php

declare(strict_types=1);

namespace Combat;

use pocketmine\player\Player;
use pocketmine\math\Vector3;

/**
 * System weryfikujący poprawność i legalność zadawanych uderzeń.
 * Sprawdza zasięg (reach) oraz kąt patrzenia gracza w momencie ataku.
 * Służy jako podstawowa ochrona przed nieuczciwymi zagraniami podczas walki.
 */
final class HitValidator {

    /**
     * Główna metoda walidująca uderzenie.
     * * @param Player $attacker Gracz atakujący
     * @param Player $victim Gracz atakowany (ofiara)
     * @param float $maxReach Maksymalny dopuszczalny zasięg (np. 3.0 lub 3.5 dla płynności)
     * @param float $maxAngle Kąt graniczny w stopniach (np. 80.0 stopni od środka wzroku)
     * @return bool True jeśli hit jest w 100% legalny
     */
    public function validateHit(Player $attacker, Player $victim, float $maxReach = 3.5, float $maxAngle = 80.0): bool {
        // 1. Podstawowe warunki życiowe
        if (!$attacker->isAlive() || !$victim->isAlive()) {
            return false;
        }

        // Pobieramy pozycje oczu (istotne dla precyzyjnych obliczeń 3D)
        $attackerEyePos = $attacker->getEyePos();
        $victimEyePos = $victim->getEyePos();

        // 2. WERYFIKACJA ZASIĘGU (REACH)
        // Obliczamy dystans uwzględniając bounding boxy (rozmiary) graczy, żeby uniknąć fałszywych detekcji
        $distance = $attackerEyePos->distance($victimEyePos);

        // Margines na opóźnienie sieciowe (ping) i ruch gracza
        if ($distance > $maxReach + 0.5) {
            return false;
        }

        // 3. WERYFIKACJA KĄTA PATRZENIA (HIT ANGLE / FIELD OF VIEW)
        // Tworzymy wektor kierunku od atakującego do ofiary
        $directionToVictim = $victimEyePos->subtractVector($attackerEyePos)->normalize();

        // Pobieramy wektor kierunku, w którym faktycznie patrzy atakujący
        $attackerLookVector = $attacker->getDirectionVector()->normalize();

        // Obliczamy dot product (iloczyn skalarny) – im bliżej 1.0, tym dokładniej celuje w gracza
        $dotProduct = $attackerLookVector->dot($directionToVictim);

        // Zamieniamy iloczyn skalarny na stopnie (kąt między wzrokiem a ofiarą)
        $angle = rad2deg(acos(max(-1.0, min(1.0, $dotProduct))));

        // Jeśli kąt jest zbyt duży (np. gracz bije kogoś, na kogo nie patrzy) – odrzucamy uderzenie
        if ($angle > $maxAngle) {
            return false;
        }

        return true;
    }
}