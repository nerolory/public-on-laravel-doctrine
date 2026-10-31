<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Contracts\UserRepository;
use App\DTO\Common\PageData;
use App\DTO\User\UserCardData;
use App\DTO\User\UserData;
use App\DTO\User\UserRowData;
use App\Entities\PersonName;
use App\Entities\User;
use App\Events\User\EmailChanged;
use App\Services\Media\ImageService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;

/**
 * Учётные записи: создание, смена email и ФИО, блокировка и удаление.
 */
final class UserService
{
    /** Ширина аватара в карточке пользователя рядом с комментарием или голосом, пикселей. */
    public const int CARD_AVATAR_WIDTH = 64;

    /**
     * @param UserRepository $users  пользователи
     * @param ImageService   $images адреса аватаров
     * @param Dispatcher     $events события
     * @param Application    $app    текущий язык для писем
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly ImageService $images,
        private readonly Dispatcher $events,
        private readonly Application $app,
    ) {
    }

    /**
     * Создаёт пользователя с готовым хешем пароля.
     */
    public function create(UserData $data, string $passwordHash): User
    {
        $user = User::create($data, $passwordHash);
        $this->users->save($user);

        return $user;
    }

    /**
     * Занят ли адрес почты; $exceptId — пользователь, чей адрес не считается.
     */
    public function emailInUse(string $email, ?int $exceptId = null): bool
    {
        return $this->users->emailInUse($email, $exceptId);
    }

    /**
     * Карточка пользователя для ленты комментариев и голосов: имя и аватар.
     */
    public function card(User $user): UserCardData
    {
        return new UserCardData(
            id: $user->persistedId(),
            name: $user->publicName(),
            avatar: $this->images->presentOrNull($user->getAvatar(), self::CARD_AVATAR_WIDTH)?->url,
        );
    }

    /**
     * Смена имени и email владельцем аккаунта; новый адрес получает письмо подтверждения.
     */
    public function update(User $user, UserData $data): void
    {
        $this->saveWithEmailCheck($user, fn () => $user->update($data));
    }

    /**
     * Правка ФИО и email администратором; новый адрес получает письмо подтверждения.
     */
    public function updateIdentity(User $user, PersonName $name, string $email): void
    {
        $this->saveWithEmailCheck($user, fn () => $user->updateIdentity($name, $email));
    }

    /**
     * Отмечает адрес подтверждённым.
     */
    public function verifyEmail(User $user): void
    {
        $user->markEmailVerified();
        $this->users->save($user);
    }

    /**
     * Страница списка пользователей для админки.
     *
     * @return PageData<UserRowData>
     */
    public function page(int $page, int $limit): PageData
    {
        return $this->users->page($page, $limit);
    }

    /**
     * Пользователь по идентификатору или null; удалённые не находятся.
     */
    public function find(int $id): ?User
    {
        return $this->users->find($id);
    }

    /**
     * Блокирует пользователя.
     */
    public function ban(User $user): void
    {
        $user->ban();
        $this->users->save($user);
    }

    /**
     * Снимает блокировку.
     */
    public function unban(User $user): void
    {
        $user->unban();
        $this->users->save($user);
    }

    /**
     * Удаляет учётную запись.
     */
    public function deleteUser(User $user): void
    {
        $user->delete();
        $this->users->save($user);
    }

    /**
     * Сохраняет пользователя; при смене адреса почты отправляет событие EmailChanged.
     */
    private function saveWithEmailCheck(User $user, callable $change): void
    {
        $previous = $user->getEmail();
        $change();
        $this->users->save($user);

        if ($user->getEmail() !== $previous) {
            $this->events->dispatch(new EmailChanged($user->persistedId(), $this->app->getLocale()));
        }
    }
}
