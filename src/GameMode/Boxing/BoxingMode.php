<?php

declare(strict_types=1);

namespace GameMode\Boxing;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use GameMode\AbstractGameMode;
use Domain\GameMode\GameModeConfig;

/**
 * Boxing game mode implementation.
 *
 * Core difference from other modes:
 *   - No player can die â€” HP resets to 20.0 after every hit
 *   - Win condition: first to reach maxHits (default 100)
 *   - Timeout win: whoever has more hits after 3 minutes
 *   - Kit: just a sword, no potions, no armor
 *
 * BoxingMode owns the active BoxingSessions map (matchId => session).
 * BoxingListener calls recordHit() and resets HP on every EntityDamageByEntityEvent.
 *
 * Registration in Plugin::onEnable():
 *   $registry->register(new BoxingMode(new BoxingConfig()));
 */
final class BoxingMode extends AbstractGameMode {

    /** @var array<string, BoxingSession> matchId => session */
    private array $sessions = [];

    public function __construct(
        private readonly BoxingConfig $config = new BoxingConfig(),
    ) {
        parent::__construct('boxing', false,false);
    }

    // -----------------------------------------------------------------------
    // GameModeInterface
    // -----------------------------------------------------------------------

    public function getName(): string        { return 'boxing'; }
    public function getDisplayName(): string { return $this->config->displayName; }

    public function getConfig(): GameModeConfig {
        return new GameModeConfig(
            matchDuration:  $this->config->matchDurationSeconds,
            startingHealth: $this->config->startingHealth,
        );
    }

    public function getInventoryTemplate(): array {
        return [
            0 => $this->buildItem('minecraft:iron_sword', enchants: $this->config->swordEnchants),
        ];
    }

    public function getArmorTemplate(): array {
        return [];  // no armor in Boxing
    }

    public function getMatchLongEffects(): array {
        return []; // no permanent effects
    }

    // -----------------------------------------------------------------------
    // Match lifecycle
    // -----------------------------------------------------------------------

    /**
     * Called by MatchManager when a Boxing match starts.
     * Creates a fresh BoxingSession for this match.
     */
    public function createSession(
        string $matchId,
        string $uuidA,
        string $nameA,
        string $uuidB,
        string $nameB,
    ): BoxingSession {
        $session = new BoxingSession($uuidA, $nameA, $uuidB, $nameB, $this->config->maxHits);
        $this->sessions[$matchId] = $session;
        return $session;
    }

    public function getSession(string $matchId): ?BoxingSession {
        return $this->sessions[$matchId] ?? null;
    }

    public function onMatchEnd(string $matchId): void {
        unset($this->sessions[$matchId]);
    }

    // -----------------------------------------------------------------------
    // Core logic â€” called by BoxingListener
    // -----------------------------------------------------------------------

    /**
     * Process a hit event. Returns the winner UUID if match should end, null otherwise.
     * BoxingListener must:
     *   1. Reset victim HP to 20 regardless of return value
     *   2. If return value != null â†’ call MatchManager::endMatch()
     *   3. Update action bar with session->getScoreLine()
     */
    public function processHit(string $matchId, string $attackerUuid): ?string {
        $session = $this->sessions[$matchId] ?? null;
        if ($session === null) return null;

        $ended = $session->recordHit($attackerUuid);
        return $ended ? $attackerUuid : null;
    }

    /**
     * Process timeout â€” returns winner UUID or null on draw.
     */
    public function processTimeout(string $matchId): ?string {
        return $this->sessions[$matchId]?->getWinnerByTimeOut();
    }

    public function getHealthResetValue(): float {
        return $this->config->healthResetValue;
    }

    public function getConfig2(): BoxingConfig {
        return $this->config;
    }

    protected function buildItem(string $itemString, array $enchants = [], int $count = 1): Item {
        $item = StringToItemParser::getInstance()->parse($itemString);

        foreach ($enchants as $enchantId => $level) {
            $enchantment = EnchantmentIdMap::getInstance()->fromId((int) $enchantId);

            if ($enchantment !== null) {
                $item->addEnchantment(new EnchantmentInstance($enchantment, (int) $level));
            }
        }

        return $item;
    }
}
