<?php

declare(strict_types=1);

namespace App\Services\Complaint;

use App\Contracts\ComplaintTarget;
use App\DTO\Common\PageData;
use App\DTO\Complaint\ComplaintData;
use App\DTO\Complaint\ComplaintGroupData;
use App\Entities\User;
use App\Enums\Complaint\ComplaintProblem;
use App\Enums\Complaint\ComplaintStatus;
use App\Events\Complaint\ComplaintFiled;
use App\Repositories\ComplaintRepository;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Жалобы на статьи, комментарии, пользователей и альбомы. Решает модератор; автоскрытие по числу жалоб
 * выполняет слушатель события ComplaintFiled.
 */
final class ComplaintService
{
    /**
     * @param ComplaintRepository $complaints жалобы
     * @param ComplaintTargets    $targets    типы объектов жалоб
     * @param Dispatcher          $events     события домена
     */
    public function __construct(
        private readonly ComplaintRepository $complaints,
        private readonly ComplaintTargets $targets,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * Почему жалобу подать нельзя; null — можно, либо объекта нет: об этом сообщит file() ответом 404.
     */
    public function fileProblem(User $reporter, ComplaintData $data): ?ComplaintProblem
    {
        $target = $this->targets->get($data->target);
        $entity = $target->findPublic($data->id);

        return match (true) {
            $entity === null => null,
            $target->isOwnedBy($entity, $reporter) => ComplaintProblem::Own,
            $this->complaints->exists($target, $entity, $reporter) => ComplaintProblem::Duplicate,
            default => null,
        };
    }

    /**
     * Подаёт жалобу читателя; что она не на своё и не повторная, проверил StoreComplaintRequest.
     *
     * @throws EntityNotFoundException объекта нет или он не виден на сайте
     * @throws ValidationException     такую же жалобу подали после проверки формы
     */
    public function file(User $reporter, ComplaintData $data): void
    {
        $target = $this->targets->get($data->target);
        $entity = $target->findPublic($data->id)
            ?? throw EntityNotFoundException::fromClassNameAndIdentifier($target->entityClass(), ['id' => $data->id]);

        if ($this->complaints->exists($target, $entity, $reporter)) {
            throw ValidationException::withMessages(['id' => __('messages.complaints.duplicate')]);
        }

        $this->complaints->save($target->complain($entity, $reporter, $data->reason, $data->text));
        $this->events->dispatch(new ComplaintFiled($target->key(), $entity));
    }

    /**
     * Очередь модерации: объекты с открытыми жалобами, сначала с наибольшим числом, при равенстве — самые давние.
     *
     * @return PageData<ComplaintGroupData>
     */
    public function queue(int $page, int $limit): PageData
    {
        // Начало страницы в общем порядке может быть из любой цели, поэтому каждая отдаёт первые объекты до её конца.
        $take = $page * $limit;
        $groups = $this->targets->all()
            ->flatMap(fn (ComplaintTarget $target): Collection => $this->labelledGroups($target, $take))
            ->sort(static fn (ComplaintGroupData $a, ComplaintGroupData $b): int => [$b->total, $a->first]
                <=> [$a->total, $b->first])
            ->values();
        $total = $this->targets->all()
            ->sum(fn (ComplaintTarget $target): int => $this->complaints->countOpenObjects($target));

        return new PageData($groups->slice(($page - 1) * $limit, $limit)->values(), total: $total);
    }

    /**
     * Вправе ли модератор принять решение: скрыть пользователя можно, только если модератор им управляет.
     * Неизвестная цель или отсутствующий объект не запрещают: их разбирают проверка полей и resolve().
     */
    public function canResolve(string $target, int $id, ComplaintStatus $decision, User $moderator): bool
    {
        if ($decision !== ComplaintStatus::Upheld || ! $this->targets->keys()->contains($target)) {
            return true;
        }

        $strategy = $this->targets->get($target);
        $entity = $strategy->find($id);

        return $entity === null || $strategy->canHide($entity, $moderator);
    }

    /**
     * Решение модератора по объекту: скрыть (пользователя — заблокировать) или отклонить жалобы.
     * Право на решение проверил ResolveComplaintRequest.
     *
     * @param ComplaintStatus $decision Upheld — скрыть объект, Dismissed — отклонить жалобы
     *
     * @throws EntityNotFoundException объекта нет
     */
    public function resolve(string $target, int $id, ComplaintStatus $decision, User $moderator): void
    {
        $strategy = $this->targets->get($target);
        $entity = $strategy->find($id) ?? throw EntityNotFoundException::fromClassNameAndIdentifier(
            $strategy->entityClass(),
            ['id' => (string) $id],
        );

        if ($decision !== ComplaintStatus::Upheld) {
            $this->close($strategy, $entity, $decision, $moderator);

            return;
        }

        $this->uphold($strategy, $entity, $moderator);
    }

    /**
     * Скрывает объект и закрывает открытые жалобы на него как удовлетворённые.
     *
     * @param User|null $moderator null — объект скрыт автоматически
     */
    public function uphold(ComplaintTarget $target, object $entity, ?User $moderator): void
    {
        $target->hide($entity);
        $this->close($target, $entity, ComplaintStatus::Upheld, $moderator);
    }

    /**
     * Закрывает открытые жалобы на объект с указанным решением.
     */
    private function close(ComplaintTarget $target, object $entity, ComplaintStatus $status, ?User $moderator): void
    {
        foreach ($this->complaints->openFor($target, $entity) as $complaint) {
            $complaint->resolve($status, $moderator);
        }

        $this->complaints->flush();
    }

    /**
     * Группы жалоб одного типа с подписями объектов.
     *
     * @return Collection<int, ComplaintGroupData>
     */
    private function labelledGroups(ComplaintTarget $target, int $limit): Collection
    {
        $groups = $this->complaints->openGroups($target, $limit);
        $labels = $target->labels($groups->map(static fn (ComplaintGroupData $group): int => $group->id));

        return $groups->map(
            static fn (ComplaintGroupData $group): ComplaintGroupData => $group->withLabel($labels->get($group->id)),
        );
    }
}
