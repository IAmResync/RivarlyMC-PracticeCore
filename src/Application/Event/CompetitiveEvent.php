<?php

declare(strict_types=1);

namespace Application\Event;

/**
 * TODO: Encja opisująca specjalny event (np. "Weekend 2v2 Tournament" lub "Daily Nodebuff Cup").
 * Przechowuje tryb gry, harmonogram, nagrody oraz zasady specyficzne dla eventu.
 * Różni się od turnieju tym, że może mieć dowolne niestandardowe zasady (np. bez ELO zmiany).
 */
final class CompetitiveEvent {

    private string $id;
    private string $name;
    private string $gameMode;
    private string $scheduledTime;

    /** @var array<string, mixed> Lista nagród przewidzianych za konkretne miejsca */
    private array $rewards;

    // Niestandardowe zasady specyficzne dla eventu
    private bool $eloChangesEnabled;
    private array $customRules;

    /**
     * @param array<string, mixed> $rewards
     * @param array<string, mixed> $customRules
     */
    public function __construct(string $id, string $name, string $gameMode, string $scheduledTime, array $rewards, bool $eloChangesEnabled = false, array $customRules = []) {
        $this->id = $id;
        $this->name = $name;
        $this->gameMode = $gameMode;
        $this->scheduledTime = $scheduledTime;
        $this->rewards = $rewards;
        $this->eloChangesEnabled = $eloChangesEnabled;
        $this->customRules = $customRules;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getGameMode(): string {
        return $this->gameMode;
    }

    public function getScheduledTime(): string {
        return $this->scheduledTime;
    }

    /**
     * @return array
     */
    public function getRewards(): array {
        return $this->rewards;
    }

    /**
     * Sprawdza, czy wyniki z tego eventu powinny wpływać na ranking ELO graczy.
     */
    public function isEloChangesEnabled(): bool {
        return $this->eloChangesEnabled;
    }

    /**
     * Pobiera specyficzną regułę na podstawie jej klucza.
     */
    public function getCustomRule(string $key, mixed $default = null): array {
        return $this->customRules[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomRules(): array {
        return $this->customRules;
    }
}
