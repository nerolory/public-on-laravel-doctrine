<?php

declare(strict_types=1);

namespace App\Http\Requests\Album;

use App\Enums\Moderation\ModerationAction;
use App\Traits\ModeratesBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Действие модератора над альбомами из списка в админке.
 */
final class ModerateAlbumsRequest extends FormRequest
{
    use ModeratesBatch;

    /**
     * Правила проверки: UUID альбомов, действие и причина отклонения.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->batchRules(
            idRules: ['required', 'uuid', 'distinct'],
            actions: Rule::enum(ModerationAction::class)->only(ModerationAction::statusChanges()->all()),
        );
    }

    /**
     * UUID выбранных альбомов.
     *
     * @return Collection<int, string>
     */
    public function uuids(): Collection
    {
        return collect($this->validated('ids'))->values();
    }
}
