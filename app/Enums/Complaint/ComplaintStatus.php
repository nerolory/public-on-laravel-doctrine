<?php

declare(strict_types=1);

namespace App\Enums\Complaint;

/** Состояние жалобы. */
enum ComplaintStatus: string
{
    /** Ждёт решения модератора. */
    case Open = 'open';

    /** Материал скрыт по жалобе. */
    case Upheld = 'upheld';

    /** Жалоба отклонена. */
    case Dismissed = 'dismissed';
}
