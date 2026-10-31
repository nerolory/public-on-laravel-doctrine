<?php

declare(strict_types=1);

namespace App\Services\Complaint\Targets;

use App\Contracts\ComplaintTarget;
use App\Contracts\UserRepository;
use App\DTO\Complaint\ComplaintLabelData;
use App\Entities\Complaint;
use App\Entities\User;
use App\Entities\UserComplaint;
use App\Enums\Complaint\ComplaintReason;
use App\Services\User\PermissionService;
use App\Support\Seo\SiteUrls;
use App\Traits\ReadsNumericId;
use Illuminate\Support\Collection;

/**
 * Жалобы на пользователей: по решению модератора пользователь блокируется.
 * Заблокировать можно только того, кем модератор вправе управлять; автоматически пользователи не блокируются.
 */
final class UserTarget implements ComplaintTarget
{
    use ReadsNumericId;

    /**
     * @param UserRepository    $users       пользователи
     * @param PermissionService $permissions права на управление пользователями
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly PermissionService $permissions,
    ) {
    }

    /**
     * Ключ цели: user.
     */
    public function key(): string
    {
        return 'user';
    }

    /**
     * Класс пользователя.
     */
    public function entityClass(): string
    {
        return User::class;
    }

    /**
     * Класс жалобы на пользователя.
     */
    public function complaintClass(): string
    {
        return UserComplaint::class;
    }

    /**
     * Поле пользователя в жалобе.
     */
    public function field(): string
    {
        return 'reportedUser';
    }

    /**
     * Неудалённый пользователь.
     */
    public function findPublic(string $id): ?object
    {
        $numeric = $this->numericId($id);
        $user = $numeric === null ? null : $this->users->find($numeric);

        return $user !== null && ! $user->isDeleted() ? $user : null;
    }

    /**
     * Пользователь в любом состоянии.
     */
    public function find(int $id): ?object
    {
        return $this->users->find($id);
    }

    /**
     * @param User $entity
     */
    public function isOwnedBy(object $entity, User $user): bool
    {
        return $entity->getAuthIdentifier() === $user->getAuthIdentifier();
    }

    /**
     * @param User $entity
     */
    public function complain(object $entity, User $reporter, ComplaintReason $reason, ?string $text): Complaint
    {
        return new UserComplaint($entity, $reporter, $reason, $text);
    }

    /**
     * @param User $entity
     */
    public function canHide(object $entity, User $moderator): bool
    {
        return $this->permissions->canManage($moderator, $entity);
    }

    /**
     * @param User $entity
     */
    public function hide(object $entity): void
    {
        $entity->ban();
    }

    /**
     * Подпись кнопки блокировки автора.
     */
    public function hideLabel(): string
    {
        return __('admin.complaints.ban');
    }

    /**
     * Пользователей блокирует только модератор.
     */
    public function hidesAutomatically(): bool
    {
        return false;
    }

    /**
     * Имя и email пользователя со ссылкой на страницу автора.
     */
    public function labels(Collection $ids): Collection
    {
        $names = $this->users->labels($ids);

        return $ids->mapWithKeys(static fn (int $id): array => [$id => new ComplaintLabelData(
            text: $names->get($id, ComplaintLabelData::MISSING_TEXT),
            url: SiteUrls::authorPath($id),
        )]);
    }
}
