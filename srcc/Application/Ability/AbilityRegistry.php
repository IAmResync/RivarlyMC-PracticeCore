<?php

declare(strict_types=1);

namespace Application\Ability;

use pocketmine\item\Item;

/**
 * Central registry of all available abilities.
 * Populated at server startup — no auto-discovery.
 *
 * Registration in Plugin::onEnable():
 *   $registry = new AbilityRegistry();
 *   $registry->register(new ComboAbility());
 *   $registry->register(new NinjaStarAbility());
 *   $registry->register(new GuardianAngelAbility());
 *   $registry->register(new RocketAbility());
 *   $registry->register(new PocketBardAbility());
 *
 * AbilityListener uses findByItem() on every PlayerItemUseEvent
 * to check if the held item matches a registered ability.
 */
final class AbilityRegistry {

    /** @var array<string, AbilityInterface> id => ability */
    private array $abilities = [];

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    public function register(AbilityInterface $ability): void {
        $id = $ability->getId();

        if (isset($this->abilities[$id])) {
            throw new \LogicException("Ability '{$id}' is already registered.");
        }

        $this->abilities[$id] = $ability;
    }

    // -----------------------------------------------------------------------
    // Retrieval
    // -----------------------------------------------------------------------

    public function get(string $id): ?AbilityInterface {
        return $this->abilities[$id] ?? null;
    }

    /**
     * Finds an ability by comparing the given item to each ability's getItem().
     * Matches on type ID only — ignores stack size and lore.
     * Returns null if no ability matches (most items won't).
     */
    public function findByItem(Item $item): ?AbilityInterface {
        foreach ($this->abilities as $ability) {
            $abilityItem = $ability->getItem();
            if ($abilityItem->getTypeId() === $item->getTypeId()) {
                return $ability;
            }
        }
        return null;
    }

    /** @return array<string, AbilityInterface> */
    public function getAll(): array {
        return $this->abilities;
    }

    /** @return list<string> */
    public function getIds(): array {
        return array_keys($this->abilities);
    }

    public function count(): int {
        return count($this->abilities);
    }
}