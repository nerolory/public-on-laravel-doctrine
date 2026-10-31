<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Contracts\AccountSelfDeletion;
use App\Contracts\UserRepository;
use App\DTO\User\AccountCapabilitiesData;
use App\DTO\User\AccountData;
use App\DTO\User\AuthorProfileData;
use App\DTO\User\ContactsData;
use App\DTO\User\ProfileData;
use App\DTO\User\SocialLinkData;
use App\Entities\Image;
use App\Entities\User;
use App\Enums\Media\ImagePurpose;
use App\Enums\Settings\SettingKey;
use App\Enums\User\ProfileField;
use App\Repositories\ArticleListRepository;
use App\Services\Legal\ConsentService;
use App\Services\Media\ImageService;
use App\Services\Settings\SettingsService;
use App\Support\Settings\PageSizes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use SplFileInfo;

/**
 * Профиль пользователя: данные для владельца и публичная карточка автора с учётом видимости полей.
 */
final class ProfileService
{
    /** Соцсети, доступные в анкете всегда. */
    private const array BASE_NETWORKS = ['vk', 'telegram', 'ok', 'youtube', 'github', 'website'];

    /** Соцсети, которые включаются в настройках сайта: имя соцсети => настройка включения. */
    private const array OPTIONAL_NETWORKS = [
        'facebook' => SettingKey::ProfileNetworkFacebook,
        'x' => SettingKey::ProfileNetworkX,
    ];

    /** Ширина аватара в личном кабинете, пикселей. */
    private const int ACCOUNT_AVATAR_WIDTH = 128;

    /** Ширина аватара на странице автора, пикселей. */
    private const int AUTHOR_AVATAR_WIDTH = 256;

    /**
     * @param UserRepository        $users       пользователи
     * @param ImageService          $images      аватары
     * @param SettingsService       $settings    включённые соцсети
     * @param AccountSelfDeletion   $deletion    удаление своей учётной записи
     * @param PermissionService     $permissions роли
     * @param ConsentService        $consents    согласия
     * @param ArticleListRepository $articles    статьи автора
     * @param PageSizes             $pageSizes   размер страницы статей
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly ImageService $images,
        private readonly SettingsService $settings,
        private readonly AccountSelfDeletion $deletion,
        private readonly PermissionService $permissions,
        private readonly ConsentService $consents,
        private readonly ArticleListRepository $articles,
        private readonly PageSizes $pageSizes,
    ) {
    }

    /**
     * Соцсети анкеты: постоянные и включённые в настройках.
     *
     * @return Collection<int, string>
     */
    public function networks(): Collection
    {
        $optional = collect(self::OPTIONAL_NETWORKS)
            ->filter(fn (SettingKey $key): bool => $this->settings->boolean($key))
            ->keys();

        return collect(self::BASE_NETWORKS)->merge($optional)->values();
    }

    /**
     * Аккаунт вошедшего посетителя; для гостя null.
     */
    public function sessionAccount(?Authenticatable $user): ?AccountData
    {
        return $user instanceof User ? $this->account($user) : null;
    }

    /**
     * Данные аккаунта для его владельца: всё, включая скрытые поля.
     */
    public function account(User $user): AccountData
    {
        return new AccountData(
            id: (int) $user->getAuthIdentifier(),
            email: $user->getEmail(),
            emailVerified: $user->getEmailVerifiedAt() !== null,
            name: $user->getName(),
            publicName: $user->publicName(),
            avatar: $this->images->presentOrNull($user->getAvatar(), self::ACCOUNT_AVATAR_WIDTH),
            profile: $user->profile(),
            networks: $this->networks(),
            capabilities: new AccountCapabilitiesData(
                deleteAccount: $this->deletion->canDeleteOwnAccount($user),
                admin: $this->permissions->canModerate($user),
                exportData: $this->consents->policy()->allowsDataExport(),
            ),
            pendingConsents: $this->consents->pending($user)->count(),
        );
    }

    /**
     * Сохраняет анкету.
     */
    public function update(User $user, ProfileData $profile): void
    {
        $user->updateProfile($profile);
        $this->users->save($user);
    }

    /**
     * Новый аватар; прежний удаляется вместе с файлами.
     */
    public function changeAvatar(User $user, SplFileInfo $file): void
    {
        $this->replaceAvatar($user, $this->images->store($file, ImagePurpose::Avatar, $user));
    }

    /**
     * Удаляет аватар вместе с файлами.
     */
    public function removeAvatar(User $user): void
    {
        $this->replaceAvatar($user, avatar: null);
    }

    /**
     * Публичная карточка автора: только поля, которые он разрешил показывать. Кто её видит, решает UserPolicy.
     */
    public function publicProfile(User $user, int $page): AuthorProfileData
    {
        $visible = static fn (ProfileField $field): bool => $user->isVisible($field);

        return new AuthorProfileData(
            id: (int) $user->getAuthIdentifier(),
            name: $user->publicName(),
            avatar: $this->images->presentOrNull($user->getAvatar(), self::AUTHOR_AVATAR_WIDTH),
            birthDate: $visible(ProfileField::BirthDate) ? $user->getBirthDate() : null,
            gender: $visible(ProfileField::Gender) ? $user->getGender()?->value : null,
            about: $visible(ProfileField::About) ? $user->getAbout() : null,
            contacts: $visible(ProfileField::Contacts) ? $user->getContacts() : new ContactsData(),
            socials: $visible(ProfileField::Socials) ? $this->socialLinks($user) : collect(),
            articles: $this->articles->publishedPage($page, $this->pageSizes->articles(), author: $user),
        );
    }

    /**
     * Открытые ссылки автора на соцсети, которые показываются в анкете.
     *
     * @return Collection<int, SocialLinkData>
     */
    private function socialLinks(User $user): Collection
    {
        return $user->getSocials()
            ->only($this->networks()->all())
            ->map(static fn (string $url, string $network): SocialLinkData => new SocialLinkData($network, $url))
            ->values();
    }

    /**
     * Заменяет аватар; прежний файл удаляется.
     */
    private function replaceAvatar(User $user, ?Image $avatar): void
    {
        $previous = $user->getAvatar();
        $user->changeAvatar($avatar);
        $this->users->save($user);

        if ($previous !== null) {
            $this->images->delete($previous);
        }
    }
}
