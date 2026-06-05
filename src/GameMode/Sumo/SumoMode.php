<?php

declare(strict_types=1);

namespace GameMode\Sumo;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use GameMode\AbstractGameMode;
use Domain\GameMode\GameModeConfig;

final class SumoMode extends AbstractGameMode {

    /** @var array<string, bool> matchId => true (active matches) */
    private array $activeMatches = [];

    public function __construct(
        private readonly SumoConfig $config = new SumoConfig(),
    ) {
        parent::__construct('sumo', false, false);
    }

    public function getName(): string        { return 'sumo'; }
    public function getDisplayName(): string { return $this->config->displayName; }

    public function getConfig(): GameModeConfig {
        return new GameModeConfig(
            matchDuration:  $this->config->matchDurationSeconds,
            startingHealth: $this->config->startingHealth,
        );
    }

    public function getInventoryTemplate(): array {
        return [
            0 => $this->buildItem('minecraft:wooden_sword', enchants: $this->config->swordEnchants),
        ];
    }

    public function getArmorTemplate(): array {
        return [];
    }

    public function getMatchLongEffects(): array {
        return [];
    }

    public function onMatchStart(string $matchId): void {
        $this->activeMatches[$matchId] = true;
    }

    public function onMatchEnd(string $matchId): void {
        unset($this->activeMatches[$matchId]);
    }

    public function isActiveSumoMatch(string $matchId): bool {
        return isset($this->activeMatches[$matchId]);
    }

    public function isDamageEnabled(): bool {
        return $this->config->damageEnabled;
    }

    public function getVoidYThreshold(): float {
        return $this->config->voidYThreshold;
    }

    public function getKnockbackMultiplier(): float {
        return $this->config->knockbackMultiplier;
    }

    private function buildItem(string $itemString, array $enchants = []): Item {
        $item = StringToItemParser::getInstance()->parse($itemString);

        foreach ($enchants as $enchantId => $level) {
            $enchantment = EnchantmentIdMap::getInstance()->fromId((int) $enchantId);

            if ($enchantment !== null) {
                $item->addEnchantment(new EnchantmentInstance($enchantment,(int) $level));
            }
        }

        return $item;
    }
}