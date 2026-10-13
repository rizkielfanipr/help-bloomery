<?php

namespace App\Exceptions\Briefing;

use RuntimeException;

/**
 * No bundle may exist for this branch/period/date — the branch is not a participant, the actor
 * is not one of its participants, or nothing is assigned (docs/daily-briefing-bundle-approval-prd.md §7, §14).
 */
class BriefingBundleUnavailableException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function branchNotParticipant(): self
    {
        return new self('not_participant', 'Cabang ini bukan peserta Daily Briefing pada tanggal tersebut.');
    }

    public static function actorNotParticipant(): self
    {
        return new self('actor_not_participant', 'Anda tidak terdaftar sebagai peserta Daily Briefing cabang ini.');
    }

    public static function nothingAssigned(): self
    {
        return new self('nothing_assigned', 'Tidak ada poin Daily Briefing yang diwajibkan untuk periode ini.');
    }
}
