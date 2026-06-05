<?php

declare(strict_types=1);

namespace GameMode\Nodebuff;

/**
 * TODO: Plik konfiguracyjny dedykowany dla ustawień trybu Nodebuff.
 * Zawiera definicje slotów ekwipunku, poziomy efektów potionów i prędkość walki.
 * Pozwala na szybką modyfikację balansu trybu bez zmiany logiki w kodzie.
 */
final class NodebuffConfig {

    public function __construct(
        // --- Definicje slotów ekwipunku ---
        public readonly int $swordSlot       = 0,
        public readonly int $potionSlot      = 1,
        public readonly int $pearlSlot       = 7,
        public readonly int $gappleSlot      = 8,

        // --- Ilości przedmiotów w kicie ---
        public readonly int $potionCount     = 16,
        public readonly int $pearlCount      = 16,
        public readonly int $gappleCount     = 1,

        // --- Poziomy i wartości efektów potionów (Zgodne z PM5) ---
        public readonly string $potionType   = 'strong_healing', // Instant Health II
        public readonly float $healPerPotion = 8.0,              // 4 pełne serca leczenia per potka
        public readonly int $speedLevel      = 2,                // Speed II w grze (amplifier 1)

        // --- Prędkość walki (Attack Cooldown) ---
        // Pozwala dostosować opóźnienie między hitami, aby walka była płynna jak na starszych wersjach
        public readonly int $attackCooldownTicks = 10, // 10 ticks = 0.5s domyślnego cooldownu (można zmniejszyć dla szybszego PvP)

        // --- Ustawienia meczu ---
        public readonly int $matchDurationSeconds = 600,
        public readonly float $startingHealth     = 20.0
    ) {}

    /**
     * Zwraca ilość punktów życia przywracaną przez miksturę.
     */
    public function getHealPerPotion(): float {
        return $this->healPerPotion;
    }

    /**
     * Zwraca wymagany cooldown ataku w tickach dla zachowania odpowiedniej prędkości walki.
     */
    public function getAttackCooldownTicks(): int {
        return $this->attackCooldownTicks;
    }
}