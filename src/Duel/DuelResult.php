<?php

declare(strict_types=1);

namespace Duel;

final class DuelResult {

    private function __construct(
        private readonly bool $success,
        private readonly ?DuelFailReason $failReason = null,
        private readonly ?string $matchId = null
    ) {}

    public static function ok(?string $matchId = null): self {
        return new self(true, null, $matchId);
    }

    public static function fail(DuelFailReason $reason): self {
        return new self(false, $reason, null);
    }

    public function isSuccess(): bool {
        return $this->success;
    }

    public function getFailReason(): ?DuelFailReason {
        return $this->failReason;
    }

    public function getMatchId(): ?string {
        return $this->matchId;
    }
}