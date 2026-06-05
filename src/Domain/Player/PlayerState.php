<?php

declare(strict_types=1);

namespace Domain\Player;

/**
 * Enum reprezentujący aktualny stan gracza.
 */
enum PlayerState: string {
    case LOBBY = 'lobby';
    case IN_QUEUE = 'in_queue';
    case IN_MATCH = 'in_match';
    case SPECTATING = 'spectating';
    case IN_TOURNAMENT = 'in_tournament';
}
