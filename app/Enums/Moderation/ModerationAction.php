<?php

declare(strict_types=1);

namespace App\Enums\Moderation;

use App\Contracts\Moderatable;
use App\Contracts\ModerationStore;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Действие модератора над статьями или альбомами из списка модерации.
 */
enum ModerationAction: string
{
    /** Вернуть материал на модерацию. */
    case Pending = 'pending';

    /** Опубликовать материал. */
    case Approve = 'approve';

    /** Отклонить материал с причиной. */
    case Reject = 'reject';

    /** Удалить материал. */
    case Delete = 'delete';

    /** Наибольшее число записей в одном действии: столько помещается на странице списка с запасом. */
    public const int BATCH_LIMIT = 100;

    /**
     * Действия, которые меняют состояние модерации; удаление выполняется отдельно.
     *
     * @return Collection<int, self>
     */
    public static function statusChanges(): Collection
    {
        return collect([self::Pending, self::Approve, self::Reject]);
    }

    /**
     * Действие, которое приводит к данному состоянию: текущий пункт списка в админке.
     */
    public static function leadingTo(ModerationStatus $status): self
    {
        return match ($status) {
            ModerationStatus::Published => self::Approve,
            ModerationStatus::Rejected => self::Reject,
            ModerationStatus::Pending => self::Pending,
        };
    }

    /**
     * Удаляет ли действие материал: удаление выполняет хранилище, остальные действия меняют состояние модерации.
     */
    public function removes(): bool
    {
        return $this === self::Delete;
    }

    /**
     * Применяет действие к материалам пакета и сохраняет результат: удаление выполняет хранилище материалов.
     *
     * @param Collection<int, Moderatable> $subjects
     */
    public function perform(Collection $subjects, ModerationStore $store, ?string $reason): void
    {
        if ($this->removes()) {
            $store->delete($subjects);

            return;
        }

        $subjects->each(fn (Moderatable $subject) => $this->apply($subject, $reason));
        $store->flush();
    }

    /**
     * Применяет действие к материалу; отклонение требует причину, удаление выполняет хранилище материалов.
     */
    public function apply(Moderatable $subject, ?string $reason): void
    {
        match ($this) {
            self::Approve => $subject->publish(),
            self::Reject => $subject->reject($reason ?? throw new LogicException('A rejection reason is required.')),
            self::Pending => $subject->sendToModeration(),
            self::Delete => throw new LogicException('Deletion is performed by the repository.'),
        };
    }
}
