<?php

declare(strict_types=1);

namespace App\DTO\Article;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Какие кнопки статьи показать посетителю.
 * Сервер проверяет каждое действие заново.
 *
 * @implements Arrayable<string, bool>
 */
final readonly class ArticlePermissionsData implements Arrayable
{
    /**
     * @param bool $edit     автор может править статью
     * @param bool $delete   автор может удалить статью в архив
     * @param bool $moderate посетитель — модератор
     * @param bool $react    посетитель может ставить реакции
     */
    public function __construct(
        public bool $edit,
        public bool $delete,
        public bool $moderate,
        public bool $react,
    ) {
    }

    /**
     * Права для ответа API.
     *
     * @return array{edit: bool, delete: bool, moderate: bool, react: bool}
     */
    public function toArray(): array
    {
        return [
            'edit' => $this->edit,
            'delete' => $this->delete,
            'moderate' => $this->moderate,
            'react' => $this->react,
        ];
    }
}
