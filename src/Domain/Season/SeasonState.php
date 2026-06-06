<?php

declare(strict_types=1);

namespace Domain\Season;

/**
 * Enum stanów sezonu: ACTIVE, ENDING (7 dni przed końcem) i FINISHED.
 * Steruje tym, czy gracz może grać rankedowo i czy nagrody są już przyznawane.
 * Używany przez SeasonManager do blokowania/odblokowywania kolejek rankingowych.
 */
enum SeasonState: string {
    case ACTIVE   = 'active';
    case ENDING   = 'ending';
    case FINISHED = 'finished';

    /**
     * Czy można grać rankedowo w tym stanie sezonu.
     */
    public function allowsRankedPlay(): bool {
        return $this === self::ACTIVE || $this === self::ENDING;
    }

    /**
     * Czy nagrody sezonowe powinny być już przyznawane.
     */
    public function isRewardPhase(): bool {
        return $this === self::FINISHED;
    }

    /**
     * Czy sezon dobiegł końca (do archiwizacji w SeasonSnapshot).
     */
    public function isOver(): bool {
        return $this === self::FINISHED;
    }
}
