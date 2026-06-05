<?php

declare(strict_types=1);

namespace Core;

use pocketmine\plugin\PluginBase;
use Application\Ability\AbilityRegistry;
use Application\Ability\impl\ComboAbility;
use Application\Ability\impl\GuardianAngelAbility;

/**
 * Glowna klasa pluginu
 */

class Plugin extends PluginBase {

    private ?\Rivarly\Core\Container $container = null;

    protected function onEnable(): void {
        $this->saveDefaultConfig();

        try {
            $bootstrap = new \Rivarly\Core\Bootstrap($this);
            $this->container = $bootstrap->init();
            
            // Register default abilities
            $this->container->abilityRegistry->register(new \Rivarly\Application\Ability\impl\ComboAbility($this->getScheduler()));
            $this->container->abilityRegistry->register(new \Rivarly\Application\Ability\impl\GuardianAngelAbility());
            
            $this->getLogger()->info("§aRivarly Practice Core has been enabled successfully!");
        } catch (\Throwable $e) {
            $this->getLogger()->error("Failed to enable Rivarly Practice Core: " . $e->getMessage());
            $this->getLogger()->error($e->getTraceAsString());
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
    }

    protected function onDisable(): void {
        if ($this->container !== null) {
            try {
                // Close database connection
                $this->container->databaseManager->close();
                
                // Stats are saved automatically when sessions end via endSession()
                // No manual flush needed for StatsCollector
                
                $this->getLogger()->info("§cRivarly Practice Core has been disabled safely.");
            } catch (\Throwable $e) {
                $this->getLogger()->error("Error during shutdown: " . $e->getMessage());
            }
        }
    }

    public function getContainer(): ?\Rivarly\Core\Container {
        return $this->container;
    }
}
