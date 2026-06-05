<?php

declare(strict_types=1);

namespace Social\Clan;

/**
 * Domain entity representing a clan.
 * Pure PHP — no PocketMine dependency.
 *
 * A clan has:
 *   - A unique tag (3-5 chars, e.g. "GG", "PRO") used in chat prefix
 *   - A leader (uuid) and a list of members (uuids)
 *   - Points accumulated from ranked wins
 *   - A color code for the clan tag display
 *   - A creation timestamp
 *
 * Chat display example:
 *   §6[GG] §fResync: gg
 */
final class Clan {

    private const MAX_MEMBERS = 20;

    /** @var list<string> member uuids (excludes leader) */
    private array $memberUuids = [];

    /** @var list<string> pending invite uuids */
    private array $pendingInvites = [];

    private int $points;
    private string $color;

    public function __construct(
        private readonly string $id,
        private readonly string $tag,
        private readonly string $name,
        private string          $leaderUuid,
        int                     $points    = 0,
        string                  $color     = '§6',
        private readonly int    $createdAt = 0,
    ) {
        $this->points = $points;
        $this->color  = $color;
    }

    // -----------------------------------------------------------------------
    // Membership
    // -----------------------------------------------------------------------

    public function addMember(string $uuid): bool {
        if ($this->isMember($uuid) || $this->isFull()) return false;
        $this->memberUuids[] = $uuid;
        $this->cancelInvite($uuid);
        return true;
    }

    public function removeMember(string $uuid): bool {
        if (!$this->isMember($uuid)) return false;
        $this->memberUuids = array_values(array_filter($this->memberUuids, fn($u) => $u !== $uuid));
        return true;
    }

    public function isMember(string $uuid): bool {
        return $uuid === $this->leaderUuid || in_array($uuid, $this->memberUuids, true);
    }

    public function isLeader(string $uuid): bool {
        return $uuid === $this->leaderUuid;
    }

    public function getMemberCount(): int {
        return count($this->memberUuids) + 1;
    }

    public function isFull(): bool {
        return $this->getMemberCount() >= self::MAX_MEMBERS;
    }

    // -----------------------------------------------------------------------
    // Leadership transfer
    // -----------------------------------------------------------------------

    public function transferLeadership(string $newLeaderUuid): bool {
        if (!$this->isMember($newLeaderUuid) || $newLeaderUuid === $this->leaderUuid) return false;
        $this->memberUuids[] = $this->leaderUuid;
        $this->leaderUuid    = $newLeaderUuid;
        $this->memberUuids   = array_values(array_filter($this->memberUuids, fn($u) => $u !== $newLeaderUuid));
        return true;
    }

    // -----------------------------------------------------------------------
    // Invites
    // -----------------------------------------------------------------------

    public function sendInvite(string $uuid): void {
        if (!in_array($uuid, $this->pendingInvites, true)) $this->pendingInvites[] = $uuid;
    }

    public function hasInvite(string $uuid): bool {
        return in_array($uuid, $this->pendingInvites, true);
    }

    public function cancelInvite(string $uuid): void {
        $this->pendingInvites = array_values(array_filter($this->pendingInvites, fn($u) => $u !== $uuid));
    }

    // -----------------------------------------------------------------------
    // Points
    // -----------------------------------------------------------------------

    public function addPoints(int $amount): void    { $this->points = max(0, $this->points + $amount); }
    public function removePoints(int $amount): void { $this->points = max(0, $this->points - $amount); }

    // -----------------------------------------------------------------------
    // Display
    // -----------------------------------------------------------------------

    public function getChatPrefix(): string { return "{$this->color}[{$this->tag}]"; }
    public function setColor(string $code): void { $this->color = $code; }

    // -----------------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------------

    public function getId(): string         { return $this->id; }
    public function getTag(): string        { return $this->tag; }
    public function getName(): string       { return $this->name; }
    public function getLeaderUuid(): string { return $this->leaderUuid; }
    public function getPoints(): int        { return $this->points; }
    public function getColor(): string      { return $this->color; }
    public function getCreatedAt(): int     { return $this->createdAt; }

    /** @return list<string> */
    public function getMemberUuids(): array { return $this->memberUuids; }

    /** @return list<string> all uuids including leader */
    public function getAllMemberUuids(): array {
        return array_merge([$this->leaderUuid], $this->memberUuids);
    }

    // -----------------------------------------------------------------------
    // Serialization
    // -----------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function toPersistenceArray(): array {
        return [
            'id'           => $this->id,
            'tag'          => $this->tag,
            'name'         => $this->name,
            'leader_uuid'  => $this->leaderUuid,
            'member_uuids' => json_encode($this->memberUuids),
            'points'       => $this->points,
            'color'        => $this->color,
            'created_at'   => $this->createdAt,
        ];
    }

    /** @param array<string, mixed> $row */
    public static function fromPersistenceArray(array $row): self {
        $clan = new self(
            id:         (string) $row['id'],
            tag:        (string) $row['tag'],
            name:       (string) $row['name'],
            leaderUuid: (string) $row['leader_uuid'],
            points:     (int)    $row['points'],
            color:      (string) $row['color'],
            createdAt:  (int)    $row['created_at'],
        );
        $clan->memberUuids = json_decode((string) $row['member_uuids'], true) ?? [];
        return $clan;
    }
}
