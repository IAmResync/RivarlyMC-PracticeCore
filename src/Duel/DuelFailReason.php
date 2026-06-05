<?php

declare(strict_types=1);

namespace Duel;

enum DuelFailReason: string {
    case UNKNOWN_GAME_MODE = 'unknown_game_mode';
    case SENDER_NOT_IN_LOBBY = 'sender_not_in_lobby';
    case RECEIVER_NOT_IN_LOBBY = 'receiver_not_in_lobby';
    case RECEIVER_NOT_ACCEPTING = 'receiver_not_accepting';
    case ALREADY_PENDING = 'already_pending';
    case REQUEST_NOT_FOUND = 'request_not_found';
    case REQUEST_EXPIRED = 'request_expired';
}