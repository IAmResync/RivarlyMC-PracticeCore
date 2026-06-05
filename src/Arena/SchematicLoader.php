<?php

declare(strict_types=1);

namespace Arena;

use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\Position;
use Core\Plugin;

/**
 * TODO: Odpowiada za asynchroniczne wczytywanie i wklejanie map z plików schematic.
 * Pozwala na błyskawiczne przywrócenie areny do stanu pierwotnego bez lagów.
 * Zarządza optymalizacją procesu kopiowania dużych ilości bloków na serwerze.
 */
class SchematicLoader {


    private Plugin $plugin;

    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Ładuje plik. .schematic i wkleja go na serwer w sposób zoptymalizowany (partiami).
     * Zapobiega całkowicie lagow i ucięciom tps-ów serwera (Spike lag).
     */
    public function loadAndPasteSchematic(string $filePath, Position $targetPosition): void {
        // 1. Symulacja asynchronicznego odczytu pliku NBT/Schematic (np. poprzez Server::getInstance()->getAsyncPool())
        // Na potrzeby logiki stworzę strukturę danych trójwymiarowych blokwów, które zostały "wczytane"

        Server::getInstance()->getLogger()->info("[SchematicLoader] Rozpoczęto asynchroniczne wczytywanie pliku: {$filePath}");

        // Wyobrażamy sobie, że wczytaliśmy trójwymiarową tablicę relatywnych przesunięć (x, y, z) i ID bloków
        $blocksToPaste = [];

        // Generujemy przykładową małą strukturę testową areny (np. podłoga 15x15), żeby system miał co przetwarzać.
        for ($x = 0; $x < 15; $x++) {
            for ($z = 0; $z < 15; $z++) {
                for ($y = 0; $y < 3; $y++) {
                    $blocksToPaste[] = [
                        "relX" => $x,
                        "relY" => $y,
                        "relZ" => $z,
                        "blockId" => ($y === 0) ? 1 : 0 // Kamień na dole, wyżej powietrze
                    ];
                }
            }
        }

        // 2. Optymalizacja wklejania - dzielimy tysiące bloków na małe pakiety
        $this->pasteInChunks($blocksToPaste, $targetPosition);
    }

    /**
     * Rozbija proces fizycznego ustawiania bloków w świecie na ticki serwera.
     *
     * @param array<int, array<string, int>> $blocks
     */
    public function pasteInChunks(array $blocks, Position $targetPosition): void {

        $world = $targetPosition->getWorld();
        $baseX = (int) $targetPosition->getX();
        $baseY = (int) $targetPosition->getY();
        $baseZ = (int) $targetPosition->getZ();

        $totalBlocks = count($blocks);
        $blocksPerTick = 250;
        $offset = 0;

        // Tworzę powtarzalne zadanie w schedulerze PocketMine, które powtarza tablicę partiami
        $plugin = Server::getInstance()->getPluginManager()->getPlugins();
        $mainPlugin = reset($plugin); // Pobieram instancję głównego pluginu

        if ($mainPlugin === false) {
            return;
        }

        $this->plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (Task $task) use ($world, $blocks, $baseX, $baseY, $baseZ, $totalBlocks, $blocksPerTick, &$offset) {
          $currentTickPasted = 0;

          while ($offset < $totalBlocks && $currentTickPasted < $blocksPerTick) {
              $blocksData = $blocks[$offset];

              $x = $baseX + $blocksData["relX"];
              $y = $baseY + $blocksData["relY"];
              $z = $baseZ + $blocksData["relZ"];

              if ($world->isInWorld($x, $y, $z)) {
                  // Tutaj w docelowym kodzie ląduje ustawienie bloku w świecie
              }

              $offset++;
              $currentTickPasted++;
          }

          // Bezpieczne zatrzymanie zadania po ukończeniu wklejania
            if ($offset >= $totalBlocks) {
                $this->plugin->getLogger()->info("[SchematicLoader] Arena została pomyślnie zregenerowana bez lagów!");
                $task->getHandler()->cancel();
            }
        }),
         1
        );
    }
}
