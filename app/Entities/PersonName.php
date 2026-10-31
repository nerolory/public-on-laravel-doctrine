<?php

declare(strict_types=1);

namespace App\Entities;

use App\Traits\NormalizesText;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Str;

/**
 * ФИО — встраиваемый объект Doctrine: колонки last_name, first_name и middle_name лежат в таблице users.
 */
#[ORM\Embeddable]
final class PersonName
{
    use NormalizesText;

    /** Длина фамилии, имени и отчества — размер колонок: помещаются двойные фамилии и длинные иностранные имена. */
    public const int PART_LENGTH = 100;

    #[ORM\Column(name: 'last_name', type: 'string', length: self::PART_LENGTH, nullable: true)]
    private ?string $lastName;

    #[ORM\Column(name: 'first_name', type: 'string', length: self::PART_LENGTH, nullable: true)]
    private ?string $firstName;

    #[ORM\Column(name: 'middle_name', type: 'string', length: self::PART_LENGTH, nullable: true)]
    private ?string $middleName;

    /**
     * Пустые и состоящие из пробелов части сохраняются как null.
     *
     * @param string|null $lastName   фамилия
     * @param string|null $firstName  имя
     * @param string|null $middleName отчество
     */
    public function __construct(?string $lastName = null, ?string $firstName = null, ?string $middleName = null)
    {
        $this->lastName = self::blankToNull($lastName);
        $this->firstName = self::blankToNull($firstName);
        $this->middleName = self::blankToNull($middleName);
    }

    /**
     * Имя из строки выборки (DQL с полями fullName.*); без ФИО — часть email до «@».
     */
    public static function displayOrEmail(
        ?string $firstName,
        ?string $middleName,
        ?string $lastName,
        string $email,
    ): string {
        return (new self($lastName, $firstName, $middleName))->displayFor($email);
    }

    /**
     * Часть адреса почты до «@»: имя по умолчанию для пользователя без ФИО.
     */
    public static function emailLocalPart(string $email): string
    {
        return Str::before($email, '@');
    }

    /**
     * Фамилия.
     */
    public function lastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * Имя.
     */
    public function firstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * Отчество.
     */
    public function middleName(): ?string
    {
        return $this->middleName;
    }

    /**
     * Не заполнена ни одна часть ФИО.
     */
    public function isEmpty(): bool
    {
        return $this->lastName === null && $this->firstName === null && $this->middleName === null;
    }

    /**
     * Имя для показа: «Имя Отчество Фамилия» без пустых частей.
     */
    public function display(): string
    {
        return implode(' ', array_filter([$this->firstName, $this->middleName, $this->lastName]));
    }

    /**
     * Имя для показа владельца адреса почты; без ФИО — часть адреса до «@».
     */
    public function displayFor(string $email): string
    {
        if ($this->isEmpty()) {
            return self::emailLocalPart($email);
        }

        return $this->display();
    }
}
