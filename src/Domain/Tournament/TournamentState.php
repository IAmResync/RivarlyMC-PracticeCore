<?php

declare(strict_types=1);

namespace Domain\Tournament;

/**
 * Reprezentuje fazę, w której aktualnie znajduje się dany turniej.
 * Zarządza przejściami między zapisami, aktywną grą a podsumowaniem wyników.
 * Służy do blokowania lub odblokowywania odpowiednich komend turniejowych.
 */
enum TournamentState: string {
    case SCHEDULED    = 'scheduled';
    case REGISTRATION = 'registration';
    case ACTIVE       = 'active';
    case FINISHED     = 'finished';

    /**
     * Czy można się zapisać do turnieju.
     */
    public function allowsRegistration(): bool {
        return $this === self::REGISTRATION;
    }

    /**
     * Czy turniej trwa (mecze są rozgrywane).
     */
    public function isRunning(): bool {
        return $this === self::ACTIVE;
    }

    /**
     * Czy turniej się zakończył (wyniki są finalne).
     */
    public function isOver(): bool {
        return $this === self::FINISHED;
    }

    /**
     * Czy turniej jest przed startem.
     */
    public function isPending(): bool {
        return $this === self::SCHEDULED || $this === self::REGISTRATION;
    }
}
