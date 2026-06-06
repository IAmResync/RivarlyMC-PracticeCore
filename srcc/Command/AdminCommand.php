<?php

declare(strict_types=1);

namespace Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use Application\Arena\ArenaPool;
use Application\Arena\ArenaLifecycle;
use Application\Season\SeasonManager;
use Config\PluginConfig;

final class AdminCommand extends Command {

    public function __construct(
        private readonly ArenaPool $arenaPool,
        private readonly ArenaLifecycle $arenaLifecycle,
        private readonly SeasonManager $seasonManager,
        private readonly PluginConfig $config
    ) {
        parent::__construct(
            name: 'rivarly',
            description: 'Główna komenda administracyjna systemu Rivarly.',
            usageMessage: '/rivarly <reload|arena|season>',
        );
        $this->setPermission('command.admin');
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender->hasPermission('command.admin')) {
            $sender->sendMessage('§cBrak uprawnień.');
            return;
        }

        $sub = strtolower($args[0] ?? 'help');

        match ($sub) {
            'reload' => $this->handleReload($sender),
            'arena'  => $this->handleArena($sender, $args),
            'season' => $this->handleSeason($sender, $args),
            default  => $sender->sendMessage('§cPoprawne użycie: /rivarly <reload|arena|season>'),
        };
    }

    private function handleReload(CommandSender $sender): void {
        $this->config->reload();
        $sender->sendMessage('§aKonfiguracja została pomyślnie przeładowana z pliku config.yml.');
    }

    private function handleArena(CommandSender $sender, array $args): void {
        $action = $args[1] ?? 'list';

        switch ($action) {
            case 'list':
                $list = $this->arenaPool->getAllArenas();
                $sender->sendMessage('§eDostępne areny:');
                foreach ($list as $name => $data) {
                    $sender->sendMessage("§7- {$name}: §a{$data['status']}");
                }
                break;
            case 'reset':
                $worldName = $args[2] ?? null;
                if ($worldName === null) {
                    $sender->sendMessage('§cPodaj nazwę świata do resetu.');
                    return;
                }
                $this->arenaLifecycle->resetArena($worldName);
                $sender->sendMessage("§aArena {$worldName} została zresetowana.");
                break;
            default:
                $sender->sendMessage('§cUżycie: /rivarly arena <list|reset <world>>');
        }
    }

    private function handleSeason(CommandSender $sender, array $args): void {
        $action = $args[1] ?? 'info';

        switch ($action) {
            case 'info':
                $sender->sendMessage("§6Sezon: §f{$this->seasonManager->getCurrentSeasonName()}");
                $sender->sendMessage("§6Status: §f{$this->seasonManager->getStatus()}");
                $sender->sendMessage("§6Pozostało: §f{$this->seasonManager->getTimeLeftString()}");
                break;
            case 'start':
                $name = $args[2] ?? 'Nowy Sezon';
                $days = (int)($args[3] ?? 30);
                $this->seasonManager->startNewSeason($name, $days);
                $sender->sendMessage("§aWystartowano nowy sezon: {$name} na {$days} dni.");
                break;
            case 'end':
                $this->seasonManager->endCurrentSeason();
                $sender->sendMessage('§cSezon został zakończony ręcznie.');
                break;
            default:
                $sender->sendMessage('§cUżycie: /rivarly season <info|start <nazwa> <dni>|end>');
        }
    }
}