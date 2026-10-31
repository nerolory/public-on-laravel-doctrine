<?php

declare(strict_types=1);

namespace App\Http\Requests\Article;

use App\Enums\Moderation\ModerationAction;
use App\Traits\ModeratesBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Действие модератора над статьями из списка в админке, в том числе удаление.
 */
final class ModerateArticlesRequest extends FormRequest
{
    use ModeratesBatch;

    /**
     * Правила проверки: идентификаторы статей, действие и причина отклонения.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->batchRules(
            idRules: ['required', 'integer', 'min:1', 'distinct'],
            actions: Rule::enum(ModerationAction::class),
        );
    }

    /**
     * Идентификаторы выбранных статей.
     *
     * @return Collection<int, int>
     */
    public function ids(): Collection
    {
        return collect($this->validated('ids'))->map(static fn (mixed $id): int => (int) $id)->values();
    }
}
