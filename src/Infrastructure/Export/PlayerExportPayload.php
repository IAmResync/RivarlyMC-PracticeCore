<?php

declare(strict_types=1);

namespace Infrastructure\Export;

/**
 * TODO: DTO z pełnym profilem gracza do wyświetlenia na stronie WWW.
 * Zawiera: ELO, dywizję, stats globalne i per-tryb, historię ELO i kosmetyki.
 * Wysyłany do Next.js przy każdej znaczącej zmianie danych gracza.
 */
final class PlayerExportPayload {

    public function __construct(
       public string $username,
       public int $elo,
       public int $kills,
       public int $deaths,
       public float $kdr
    ) {}

    /**
     * Zamienia obiekt na czystą tablicę gotową pod json_encode.
     */
    public function toArray(): array {
        return [
            'username' => $this->username,
            'elo' => $this->elo,
            'stats' => [
                'kills' => $this->kills,
                'deaths' => $this->deaths,
                'kdr' => $this->kdr
            ]
        ];
    }
}
