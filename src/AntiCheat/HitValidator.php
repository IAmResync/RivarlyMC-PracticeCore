<?php

declare(strict_types=1);

namespace AntiCheat;

use pocketmine\player\Player;
use Application\Match\MatchManager;

/**
 * Pierwsza linia antycheatu – waliduje legalność uderzenia ZANIM trafi do systemu obrażeń.
 * Jeśli walidacja nie przejdzie, CombatListener powinien anulować event.
 *
 * Spina ReachChecker + CpsLimiter w jedno miejsce decyzji.
 * MatchManager sprawdza czy obaj gracze są w tym samym meczu.
 *
 * Użycie (w CombatListener):
 *   $result = $validator->validate($attacker, $victim);
 *   if (!$result->isValid()) {
 *       $event->cancel();
 *       return;
 *   }
 */
final class HitValidator {

    public function __construct(
        private readonly ReachChecker $reachChecker,
        private readonly CpsLimiter   $cpsLimiter,
        private readonly MatchManager  $matchManager,
    ) {}

    /**
     * Waliduje uderzenie. Zwraca HitValidationResult z wynikiem i powodem odrzucenia.
     */
    public function validate(Player $attacker, Player $victim): HitValidationResult {
        $attackerUuid = $attacker->getUniqueId()->toString();
        $victimUuid   = $victim->getUniqueId()->toString();

        // 1. Obaj gracze muszą być w tym samym aktywnym meczu
        $attackerMatch = $this->matchManager->getMatchByPlayer($attackerUuid);
        $victimMatch   = $this->matchManager->getMatchByPlayer($victimUuid);

        if ($attackerMatch === null || $victimMatch === null) {
            return HitValidationResult::reject('Jeden z graczy nie jest w meczu');
        }

        if ($attackerMatch->getMatchId() !== $victimMatch->getMatchId()) {
            return HitValidationResult::reject('Gracze są w różnych meczach');
        }

        if (!$attackerMatch->isActive()) {
            return HitValidationResult::reject('Mecz nie jest aktywny (grace period lub nie rozpoczęty)');
        }

        // 2. Sprawdź reach
        if ($this->reachChecker->isSuspicious($attacker, $victim)) {
            $dist = $this->reachChecker->check($attacker, $victim);
            return HitValidationResult::rejectSuspicious(
                "Reach: {$dist} bloków (zbyt daleko)",
                HitValidationResult::FLAG_REACH
            );
        }

        // 3. Sprawdź CPS (rejestrujemy kliknięcie i sprawdzamy)
        $cps = $this->cpsLimiter->recordClick($attackerUuid);
        if ($this->cpsLimiter->isSuspicious($attackerUuid)) {
            return HitValidationResult::rejectSuspicious(
                "CPS: {$cps} (powyżej limitu)",
                HitValidationResult::FLAG_CPS
            );
        }

        return HitValidationResult::accept($cps, $this->reachChecker->check($attacker, $victim));
    }
}

// ---------------------------------------------------------------------------
// Value object wyniku walidacji
// ---------------------------------------------------------------------------

final class HitValidationResult {

    public const FLAG_REACH = 'reach';
    public const FLAG_CPS   = 'cps';
    public const FLAG_NONE  = 'none';

    private function __construct(
        public readonly bool    $valid,
        public readonly string  $reason,
        public readonly string  $flagType,
        public readonly bool    $isSuspicious,
        public readonly int     $cps,
        public readonly float   $reach,
    ) {}

    public static function accept(int $cps, float $reach): self {
        return new self(
            valid:         true,
            reason:        'ok',
            flagType:      self::FLAG_NONE,
            isSuspicious:  false,
            cps:           $cps,
            reach:         $reach,
        );
    }

    public static function reject(string $reason): self {
        return new self(
            valid:         false,
            reason:        $reason,
            flagType:      self::FLAG_NONE,
            isSuspicious:  false,
            cps:           0,
            reach:         0.0,
        );
    }

    public static function rejectSuspicious(string $reason, string $flagType): self {
        return new self(
            valid:         false,
            reason:        $reason,
            flagType:      $flagType,
            isSuspicious:  true,
            cps:           0,
            reach:         0.0,
        );
    }

    public function isValid(): bool {
        return $this->valid;
    }
}
