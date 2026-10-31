<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Complaint\ComplaintLabelData;
use App\Entities\Complaint;
use App\Entities\User;
use App\Enums\Complaint\ComplaintReason;
use App\Services\Complaint\ComplaintTargets;
use Illuminate\Support\Collection;

/**
 * Объект, на который жалуются: статья, комментарий, пользователь или альбом (стратегия).
 * Новый тип цели добавляется отдельным классом и регистрируется в {@see ComplaintTargets}.
 */
interface ComplaintTarget
{
    /**
     * Ключ цели в запросах и в колонке target таблицы complaints.
     */
    public function key(): string;

    /**
     * Класс сущности объекта: нужен для сообщения, если объекта нет.
     *
     * @return class-string
     */
    public function entityClass(): string;

    /**
     * Класс жалобы на этот тип объекта.
     *
     * @return class-string<Complaint>
     */
    public function complaintClass(): string;

    /**
     * Поле связи с объектом в классе жалобы.
     */
    public function field(): string;

    /**
     * Объект, который виден посетителям сайта; на скрытое и неопубликованное не жалуются.
     *
     * @param string $id идентификатор с сайта: число или UUID
     */
    public function findPublic(string $id): ?object;

    /**
     * Объект по идентификатору из очереди модерации, в любом состоянии.
     */
    public function find(int $id): ?object;

    /**
     * Принадлежит ли объект пользователю: на своё жаловаться нельзя.
     */
    public function isOwnedBy(object $entity, User $user): bool;

    /**
     * Новая жалоба на объект.
     */
    public function complain(object $entity, User $reporter, ComplaintReason $reason, ?string $text): Complaint;

    /**
     * Может ли модератор скрыть объект.
     */
    public function canHide(object $entity, User $moderator): bool;

    /**
     * Скрывает объект с сайта.
     */
    public function hide(object $entity): void;

    /**
     * Подпись кнопки решения «скрыть» в очереди модерации.
     */
    public function hideLabel(): string;

    /**
     * Скрывается ли объект автоматически, когда жалоб набирается больше порога из настроек.
     */
    public function hidesAutomatically(): bool;

    /**
     * Подписи и ссылки для очереди модерации, по одному запросу на тип объекта.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, ComplaintLabelData> подписи по идентификатору объекта
     */
    public function labels(Collection $ids): Collection;
}
