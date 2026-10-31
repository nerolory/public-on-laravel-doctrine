<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\DTO\Article\ArticleListItemData;
use App\DTO\Common\PageData;
use App\DTO\Media\ImageData;
use DateTimeImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Публичная карточка автора: только поля, которые он разрешил показывать, и его статьи.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AuthorProfileData implements Arrayable
{
    /**
     * @param int                             $id        идентификатор автора
     * @param string                          $name      публичное имя
     * @param ImageData|null                  $avatar    аватар
     * @param DateTimeImmutable|null          $birthDate дата рождения, если открыта
     * @param string|null                     $gender    пол, если открыт
     * @param string|null                     $about     текст «О себе», если открыт
     * @param ContactsData                    $contacts  открытые контакты
     * @param Collection<int, SocialLinkData> $socials   открытые ссылки на соцсети
     * @param PageData<ArticleListItemData>   $articles  страница опубликованных статей автора
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?ImageData $avatar,
        public ?DateTimeImmutable $birthDate,
        public ?string $gender,
        public ?string $about,
        public ContactsData $contacts,
        public Collection $socials,
        public PageData $articles,
    ) {
    }

    /**
     * Карточка для ответа API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar' => $this->avatar?->toArray(),
            'birth_date' => $this->birthDate?->format('Y-m-d'),
            'gender' => $this->gender,
            'about' => $this->about,
            'contacts' => (object) $this->contacts->toArray(),
            'socials' => (object) $this->socials
                ->mapWithKeys(static fn (SocialLinkData $link): array => [$link->network => $link->url])
                ->all(),
            'articles' => $this->articles->toArray(),
        ];
    }
}
