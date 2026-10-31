<?php

declare(strict_types=1);

namespace App\Http\Requests\Comment;

use App\Entities\Article;
use App\Entities\Comment;
use App\Services\Comment\CommentService;
use App\Support\Settings\ContentLimits;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Текст нового комментария или правки; для ответа — видимый комментарий той же статьи.
 */
final class SaveCommentRequest extends FormRequest
{
    /**
     * Правила проверки: длина текста берётся из настроек сайта.
     *
     * @return array<string, mixed>
     */
    public function rules(ContentLimits $limits, CommentService $comments): array
    {
        return [
            'body' => ['required', 'string', 'max:' . $limits->commentLength()],
            'reply_to' => ['nullable', 'integer', 'min:1', $this->replyTargetRule($comments)],
        ];
    }

    /**
     * Текст комментария без пробелов по краям.
     */
    public function body(): string
    {
        return trim((string) $this->validated('body'));
    }

    /**
     * Номер комментария, на который отвечают; null для комментария без ответа.
     */
    public function replyToId(): ?int
    {
        $id = $this->validated('reply_to');

        return $id === null ? null : (int) $id;
    }

    /**
     * Отвечать можно только на видимый комментарий статьи из адреса.
     */
    private function replyTargetRule(CommentService $comments): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($comments): void {
            if ($this->findReplyTarget($comments, $value) === null) {
                $fail(__('messages.comments.reply_unavailable'));
            }
        };
    }

    /**
     * Видимый комментарий статьи из адреса с таким номером или null.
     */
    private function findReplyTarget(CommentService $comments, mixed $id): ?Comment
    {
        $article = $this->route('article');

        return $article instanceof Article && is_numeric($id) ? $comments->replyTarget($article, (int) $id) : null;
    }
}
