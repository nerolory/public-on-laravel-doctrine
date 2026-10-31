<?php

declare(strict_types=1);

namespace App\DTO\Comment;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Что текущий посетитель может сделать с комментарием.
 *
 * @implements Arrayable<string, bool>
 */
final readonly class CommentPermissionsData implements Arrayable
{
    /**
     * @param bool $edit   править текст
     * @param bool $delete удалить
     * @param bool $reply  ответить
     * @param bool $react  поставить реакцию
     */
    public function __construct(
        public bool $edit,
        public bool $delete,
        public bool $reply,
        public bool $react,
    ) {
    }

    /**
     * Права для ответа API.
     *
     * @return array{edit: bool, delete: bool, reply: bool, react: bool}
     */
    public function toArray(): array
    {
        return ['edit' => $this->edit, 'delete' => $this->delete, 'reply' => $this->reply, 'react' => $this->react];
    }
}
