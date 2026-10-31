<?php

declare(strict_types=1);

namespace App\Events\Complaint;

/**
 * Читатель подал жалобу на объект.
 */
final readonly class ComplaintFiled
{
    /**
     * @param string $target ключ цели: article, comment, user, album
     * @param object $entity объект жалобы
     */
    public function __construct(
        public string $target,
        public object $entity,
    ) {
    }
}
