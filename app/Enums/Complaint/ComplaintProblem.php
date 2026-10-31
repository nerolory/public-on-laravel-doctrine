<?php

declare(strict_types=1);

namespace App\Enums\Complaint;

/**
 * Причина, по которой жалобу подать нельзя. Значение — ключ сообщения в messages.complaints.
 */
enum ComplaintProblem: string
{
    /** На собственную публикацию или на себя. */
    case Own = 'own';

    /** Жалоба на этот объект уже подана. */
    case Duplicate = 'duplicate';
}
