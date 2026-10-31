<?php

declare(strict_types=1);

namespace App\Http\Requests\Article;

use App\Enums\Moderation\ModerationStatus;
use App\Traits\RequiresActiveUser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Удаление статьи автором: статья уходит в архив, причина обязательна.
 */
final class ArchiveArticleRequest extends FormRequest
{
    use RequiresActiveUser;

    /**
     * Правила проверки причины.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:' . ModerationStatus::REASON_LENGTH]];
    }

    /**
     * Причина удаления.
     */
    public function reason(): string
    {
        return $this->validated('reason');
    }
}
