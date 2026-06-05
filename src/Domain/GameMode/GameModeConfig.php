<?php

declare(strict_types=1);

namespace Domain\GameMode;

/**
 * TODO: Przechowuje szczegółowe ustawienia dla konkretnego trybu gry.
 * Zawiera informacje o efektach potek, zestawach startowych (kits) i zasadach PvP.
 * Jest wykorzystywany przez system meczowy do konfiguracji areny pod dany tryb.
 */
class GameModeConfig {

    private int $maxDurationSecounds;
    private bool $allowBuilding;
    private bool $allowBreaking;
    private bool $dropItemsOnDeath;
    private float $maxHealth;
    private bool $hasHunger;
    private bool $allowPotions;
    private bool $allowGoldenApples;
    private bool $allowEnderPearls;
    private int $minPlayers;
    private int $maxPlayers;
    private bool $enableComboReset;
    private float $knockbackMultiplier;

    private int $matchDuration;
    private float $StartingHealth;

    /**
     * Konstruktor przyjmujący pełen pakiet konfiguracyjny trybu gry.
     */

    public function __construct(
     int $maxDurationSecounds = 1800,
     bool $allowBuilding = false,
     bool $allowBreaking = false,
     bool $dropItemsOnDeath = false,
     float $maxHealth = 20.0,
     bool $hasHunger = true,
     bool $allowPotions = true,
     bool $allowGoldenApples = true,
     bool $allowEnderPearls = true,
     int $minPlayers = 2,
     int $maxPlayers = 2,
     bool $enableComboReset = false,
     float $knockbackMultiplier = 1.0,
     int $matchDuration = 300,
     float $StartingHealth = 20.0
    ) {
        $this->maxDurationSecounds = $maxDurationSecounds;
        $this->allowBuilding = $allowBuilding;
        $this->allowBreaking = $allowBreaking;
        $this->dropItemsOnDeath = $dropItemsOnDeath;
        $this->maxHealth = $maxHealth;
        $this->hasHunger = $hasHunger;
        $this->allowPotions = $allowPotions;
        $this->allowGoldenApples = $allowGoldenApples;
        $this->allowEnderPearls = $allowEnderPearls;
        $this->minPlayers = $minPlayers;
        $this->maxPlayers = $maxPlayers;
        $this->enableComboReset = $enableComboReset;
        $this->knockbackMultiplier = $knockbackMultiplier;
        $this->matchDuration;
        $this->StartingHealth;
    }

    public function getMaxDurationSecounds(): int {
        return $this->maxDurationSecounds;
    }

    public function canBuild(): bool {
        return $this->allowBuilding;
    }

    public function canBreak(): bool {
        return $this->allowBreaking;
    }

    public function shouldDropItemsOnDeath(): bool {
        return $this->dropItemsOnDeath;
    }

    public function getMaxHealth() : float {
        return $this->maxHealth;
    }

    public function hasHunger(): bool {
        return $this->hasHunger;
    }

    public function arePotionsAllowed(): bool {
        return $this->allowPotions;
    }

    public function areGoldenApplesAllowed(): bool {
        return $this->allowGoldenApples;
    }

    public function areEnderPearlsAllowed(): bool {
        return $this->allowEnderPearls;
    }

    public function getMinPlayers(): int {
        return $this->minPlayers;
    }

    public function getMaxPlayers(): int {
        return $this->maxPlayers;
    }

    public function isComboResetEnabled(): bool {
        return $this->enableComboReset;
    }

    public function getKnockbackMultiplier() : float {
        return $this->knockbackMultiplier;
    }

    public function getMatchDuration(): int {
        return $this->matchDuration;
    }

    public function getStartingHealth(): float {
        return $this->StartingHealth;
    }
}
