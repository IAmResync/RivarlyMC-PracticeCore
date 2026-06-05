<?php

declare(strict_types=1);

namespace AntiCheat;

use pocketmine\player\Player;
use Config\PluginConfig;

/**
 * Sprawdza czy dystans między graczami w momencie uderzenia jest w dopuszczalnym zakresie.
 * Utrzymuje rolling buffer ostatnich N pozycji każdego gracza w celu kompensacji lagów.
 */
final class ReachChecker {

    private const BUFFER_SIZE = 10;

    private float $maxReach;
    private float $buffer;

    /**
     * @var array<string, list<array{x: float, y: float, z: float, t: float}>>
     * uuid => ostatnie N pozycji ofiary z timestampem
     */
    private array $positionBuffers = [];

    public function __construct(PluginConfig $config) {
        // NAPRAWIONO: Integracja z nowo dopisanymi metodami w PluginConfig
        $this->maxReach = $config->getMaxReach();
        $this->buffer   = $config->getReachSuspiciousBuffer();
    }

    // -----------------------------------------------------------------------
    // Publiczne API
    // -----------------------------------------------------------------------

    /**
     * Rejestruje aktualną pozycję gracza do rolling buffera.
     * Wywoływane przez PlayerMoveEvent w celu ciągłego śledzenia śladu pozycji.
     */
    public function updatePosition(Player $player): void {
        $uuid = $player->getUniqueId()->toString();

        // ZOPTYMALIZOWANO: Wyciągamy czyste zmienne liczbowe zamiast obiektu Position (brak memory leak)
        $x = $player->getLocation()->getX();
        $y = $player->getLocation()->getY();
        $z = $player->getLocation()->getZ();

        if (!isset($this->positionBuffers[$uuid])) {
            $this->positionBuffers[$uuid] = [];
        }

        $this->positionBuffers[$uuid][] = [
            'x' => $x,
            'y' => $y,
            'z' => $z,
            't' => microtime(true),
        ];

        if (count($this->positionBuffers[$uuid]) > self::BUFFER_SIZE) {
            array_shift($this->positionBuffers[$uuid]);
        }
    }

    /**
     * Sprawdza dystans między atakującym a ofiarą z uwzględnieniem lagów.
     * Zwraca minimalny dystans znaleziony w buforze pozycji.
     */
    public function check(Player $attacker, Player $victim): float {
        $attackerPos = $attacker->getLocation();
        $attackerX   = $attackerPos->getX();
        $attackerY   = $attackerPos->getY();
        $attackerZ   = $attackerPos->getZ();

        $victimUuid   = $victim->getUniqueId()->toString();
        // POPRAWIONO LOGIKĘ: Analizujemy historię pozycji OFIARY, a nie atakującego!
        $victimBuffer = $this->positionBuffers[$victimUuid] ?? [];

        // Brak buffera → użyj aktualnej pozycji obu graczy
        if (empty($victimBuffer)) {
            $vPos = $victim->getLocation();
            return $this->distance3D($attackerX, $attackerY, $attackerZ, $vPos->getX(), $vPos->getY(), $vPos->getZ());
        }

        // Znajdź minimalny dystans z buffera historii poruszania się ofiary (kompensacja lagów)
        $minDist = PHP_FLOAT_MAX;
        foreach ($victimBuffer as $snapshot) {
            $d = $this->distance3D($attackerX, $attackerY, $attackerZ, $snapshot['x'], $snapshot['y'], $snapshot['z']);
            if ($d < $minDist) {
                $minDist = $d;
            }
        }

        return round($minDist, 4);
    }

    /**
     * Czy dystans jest w dozwolonym zakresie (z buforem kompensacji).
     */
    public function isWithinReach(Player $attacker, Player $victim): bool {
        return $this->check($attacker, $victim) <= ($this->maxReach + $this->buffer);
    }

    /**
     * Czy dystans jest podejrzanie duży (potencjalny reach hack).
     */
    public function isSuspicious(Player $attacker, Player $victim): bool {
        return $this->check($attacker, $victim) > ($this->maxReach + $this->buffer);
    }

    /**
     * Czyści bufer gracza po wylogowaniu lub zakończeniu meczu.
     */
    public function clearPlayer(Player $player): void {
        unset($this->positionBuffers[$player->getUniqueId()->toString()]);
    }

    // -----------------------------------------------------------------------
    // Pomocnicze
    // -----------------------------------------------------------------------

    private function distance3D(float $x1, float $y1, float $z1, float $x2, float $y2, float $z2): float {
        return sqrt(($x2 - $x1) ** 2 + ($y2 - $y1) ** 2 + ($z2 - $z1) ** 2);
    }
}