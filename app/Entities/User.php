<?php

declare(strict_types=1);

namespace App\Entities;

use App\DTO\User\ContactsData;
use App\DTO\User\ProfileData;
use App\DTO\User\UserData;
use App\Enums\User\Gender;
use App\Enums\User\ProfileField;
use App\Traits\NormalizesText;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Collection;
use LaravelDoctrine\ORM\Auth\Authenticatable;
use LaravelDoctrine\ORM\Contracts\UrlRoutable;
use LaravelDoctrine\ORM\Notifications\Notifiable;

/**
 * Пользователь: учётные данные, анкета профиля и её видимость, блокировка и мягкое удаление.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\AttributeOverrides([
    new ORM\AttributeOverride(
        name: 'rememberToken',
        column: new ORM\Column(
            name: 'remember_token',
            type: 'string',
            length: self::REMEMBER_TOKEN_LENGTH,
            nullable: true,
        ),
    ),
])]
class User implements AuthenticatableContract, CanResetPasswordContract, UrlRoutable
{
    use Authenticatable;
    use CanResetPassword;
    use NormalizesText;
    use Notifiable;

    /** Длина адреса почты — размер колонки email: адрес по RFC 5321 не длиннее 254 символов. */
    public const int EMAIL_LENGTH = 255;

    /** Длина ссылки на страницу в соцсети: адреса профилей короче, 255 — с запасом под длинные имена. */
    public const int SOCIAL_URL_LENGTH = 255;

    /** Длина текста «О себе»: несколько абзацев, как у комментария. */
    public const int ABOUT_LENGTH = 2000;

    /** Длина телефона в контактах: международный формат с пробелами и скобками. */
    public const int PHONE_LENGTH = 30;

    /** Наименьшая длина телефона: пять цифр у самых коротких городских номеров. */
    public const int PHONE_MIN_LENGTH = 5;

    /** Длина пола — размер колонки gender: значения перечисления Gender. */
    public const int GENDER_LENGTH = 10;

    /** Длина токена «запомнить меня» — размер колонки remember_token, как в стандартной миграции Laravel. */
    public const int REMEMBER_TOKEN_LENGTH = 100;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::EMAIL_LENGTH, unique: true)]
    private string $email;

    #[ORM\Column(name: 'email_verified_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    #[ORM\Column(name: 'banned_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $bannedAt = null;

    #[ORM\Embedded(class: PersonName::class, columnPrefix: false)]
    private PersonName $fullName;

    #[ORM\Column(name: 'birth_date', type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: 'string', length: self::GENDER_LENGTH, nullable: true, enumType: Gender::class)]
    private ?Gender $gender = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $about = null;

    /** @var array{phone?: string, email?: string}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contacts = null;

    /** @var array<string, string>|null ссылки на страницы в соцсетях по ключу сети */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $socials = null;

    /** @var array<string, bool>|null какие поля профиля видны всем, по значению ProfileField */
    #[ORM\Column(name: 'profile_visibility', type: 'json', nullable: true)]
    private ?array $visibility = null;

    #[ORM\ManyToOne(targetEntity: Image::class)]
    #[ORM\JoinColumn(name: 'avatar_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Image $avatar = null;

    /**
     * Новый пользователь; имя из регистрации становится полем «Имя» в ФИО.
     */
    public static function create(UserData $data, string $passwordHash): self
    {
        $user = new self();
        $user->fullName = new PersonName(firstName: $data->name);
        $user->email = $data->email;
        $user->setPassword($passwordHash);
        $user->setRememberToken(value: null);
        $user->createdAt = new DateTimeImmutable();
        $user->updatedAt = $user->createdAt;

        return $user;
    }

    /**
     * Ключ записи в адресе: id. Запись ищется запросом, поэтому к ней применяются фильтры Doctrine.
     */
    public static function getRouteKeyNameStatic(): string
    {
        return 'id';
    }

    /**
     * Смена email и имени. Новый email нужно подтвердить заново.
     */
    public function update(UserData $data): void
    {
        $this->changeEmail($data->email);
        $this->fullName = new PersonName($this->fullName->lastName(), $data->name, $this->fullName->middleName());
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Правка ФИО и email администратором.
     */
    public function updateIdentity(PersonName $name, string $email): void
    {
        $this->changeEmail($email);
        $this->fullName = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Анкета из личного кабинета: пустые контакты и ссылки не сохраняются, видимость задаётся по каждому полю.
     */
    public function updateProfile(ProfileData $profile): void
    {
        $contacts = $profile->contacts->toArray();
        $socials = $profile->socials->reject(static fn (?string $url): bool => self::blankToNull($url) === null);

        $this->fullName = $profile->name;
        $this->birthDate = $profile->birthDate;
        $this->gender = $profile->gender;
        $this->about = self::blankToNull($profile->about);
        // Пустые JSON-колонки хранятся как NULL.
        $this->contacts = $contacts === [] ? null : $contacts;
        $this->socials = $socials->isEmpty() ? null : $socials->all();
        $this->visibility = collect(ProfileField::cases())
            ->mapWithKeys(static fn (ProfileField $field): array => [
                $field->value => $profile->visibility->get($field->value) === true,
            ])
            ->all();
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Анкета профиля целиком, включая скрытые поля.
     */
    public function profile(): ProfileData
    {
        return new ProfileData(
            name: $this->fullName,
            birthDate: $this->birthDate,
            gender: $this->gender,
            about: $this->about,
            contacts: $this->getContacts(),
            socials: $this->getSocials(),
            visibility: $this->getVisibility(),
        );
    }

    /**
     * Новый аватар или его удаление (null).
     */
    public function changeAvatar(?Image $avatar): void
    {
        $this->avatar = $avatar;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Отмечает email подтверждённым; повторная отметка дату не меняет.
     */
    public function markEmailVerified(): void
    {
        $this->emailVerifiedAt ??= new DateTimeImmutable();
    }

    /**
     * Полное имя для владельца аккаунта и администраторов; без ФИО — часть email до «@».
     */
    public function getName(): string
    {
        return $this->fullName->displayFor($this->email);
    }

    /**
     * Имя для посетителей: ФИО, если пользователь разрешил его показывать; без него — часть email до «@».
     */
    public function publicName(): string
    {
        if (! $this->isVisible(ProfileField::Name)) {
            return PersonName::emailLocalPart($this->email);
        }

        return $this->fullName->displayFor($this->email);
    }

    /**
     * Та же ли это учётная запись: сравнение по номеру пользователя.
     */
    public function is(?self $other): bool
    {
        return $other !== null && $other->getAuthIdentifier() === $this->getAuthIdentifier();
    }

    /**
     * Разрешил ли пользователь показывать поле профиля посетителям.
     */
    public function isVisible(ProfileField $field): bool
    {
        // Поле без отметки в колонке profile_visibility скрыто.
        return isset($this->visibility[$field->value]) && $this->visibility[$field->value] === true;
    }

    /**
     * Автор правки для журнала версий: Gedmo LoggableListener::setUsername пишет в article_log_entries.username
     * номер пользователя, без персональных данных.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->getAuthIdentifier();
    }

    /**
     * Адрес почты.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Фамилия, имя и отчество.
     */
    public function getFullName(): PersonName
    {
        return $this->fullName;
    }

    /**
     * Дата рождения.
     */
    public function getBirthDate(): ?DateTimeImmutable
    {
        return $this->birthDate;
    }

    /**
     * Пол.
     */
    public function getGender(): ?Gender
    {
        return $this->gender;
    }

    /**
     * Текст «О себе».
     */
    public function getAbout(): ?string
    {
        return $this->about;
    }

    /**
     * Контакты: телефон и почта для связи.
     */
    public function getContacts(): ContactsData
    {
        return new ContactsData(phone: $this->contacts['phone'] ?? null, email: $this->contacts['email'] ?? null);
    }

    /**
     * Ссылки на соцсети по ключу сети.
     *
     * @return Collection<string, string>
     */
    public function getSocials(): Collection
    {
        return collect($this->socials);
    }

    /**
     * Видимость каждого поля профиля по значению ProfileField.
     *
     * @return Collection<string, bool>
     */
    public function getVisibility(): Collection
    {
        return collect(ProfileField::cases())
            ->mapWithKeys(fn (ProfileField $field): array => [$field->value => $this->isVisible($field)]);
    }

    /**
     * Аватар.
     */
    public function getAvatar(): ?Image
    {
        return $this->avatar;
    }

    /**
     * Дата регистрации.
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Дата подтверждения email; null — не подтверждён.
     */
    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    /**
     * Удалена ли учётная запись.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Мягкое удаление: запись остаётся, вход закрыт.
     */
    public function delete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
        $this->updatedAt = $this->deletedAt;
        $this->setRememberToken(value: null);
    }

    /**
     * Блокировка: сбрасывает запоминание входа.
     */
    public function ban(): void
    {
        $this->bannedAt = new DateTimeImmutable();
        $this->setRememberToken(value: null);
    }

    /**
     * Снятие блокировки.
     */
    public function unban(): void
    {
        $this->bannedAt = null;
    }

    /**
     * Заблокирован ли пользователь.
     */
    public function isBanned(): bool
    {
        return $this->bannedAt !== null;
    }

    private function changeEmail(string $email): void
    {
        if ($this->email !== $email) {
            $this->emailVerifiedAt = null;
        }

        $this->email = $email;
    }
}
