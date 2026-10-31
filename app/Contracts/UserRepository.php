<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\Common\PageData;
use App\DTO\User\UserCountsData;
use App\DTO\User\UserRowData;
use App\Entities\User;
use Illuminate\Support\Collection;

/**
 * Хранилище пользователей.
 */
interface UserRepository
{
    /**
     * Пользователь по идентификатору; удалённые не находятся.
     */
    public function find(int $id): ?User;

    /**
     * Пользователь по адресу почты.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Занят ли адрес почты, в том числе удалённым пользователем.
     */
    public function emailInUse(string $email, ?int $exceptId = null): bool;

    /**
     * Сохраняет пользователя.
     */
    public function save(User $user): void;

    /**
     * Страница списка пользователей для админки, новые первыми.
     *
     * @return PageData<UserRowData>
     */
    public function page(int $page, int $limit): PageData;

    /**
     * Число пользователей и заблокированных среди них.
     */
    public function counts(): UserCountsData;

    /**
     * Имя и email пользователей по идентификатору для списков в админке.
     *
     * @param Collection<int, int> $ids
     *
     * @return Collection<int, string>
     */
    public function labels(Collection $ids): Collection;
}
