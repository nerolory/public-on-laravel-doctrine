<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * Сеть, в которую можно поделиться страницей. Значение совпадает с ключом адреса в config/services.php
 * и с частью ключа share.{сеть} в настройках.
 */
enum ShareNetwork: string
{
    /** ВКонтакте. */
    case Vk = 'vk';

    /** Одноклассники. */
    case Ok = 'ok';

    /** Telegram. */
    case Telegram = 'telegram';

    /** Facebook. */
    case Facebook = 'facebook';

    /** X. */
    case X = 'x';
}
