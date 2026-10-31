<?php

declare(strict_types=1);

namespace App\Http\Requests\Legal;

use App\Enums\Legal\ConsentType;
use App\Services\Legal\ConsentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Принятие согласий и отзыв добровольных согласий в настройках подписок.
 */
final class UpdateConsentsRequest extends FormRequest
{
    /**
     * Правила проверки: отзывать можно только добровольные согласия.
     *
     * @return array<string, mixed>
     */
    public function rules(ConsentService $consents): array
    {
        $available = $consents->availableTypes();
        $revocable = collect(ConsentType::cases())
            ->filter(static fn (ConsentType $type): bool => $type->revocableInSettings())
            ->map(static fn (ConsentType $type): string => $type->value)
            ->intersect($available);

        return [
            'accept' => ['array'],
            'accept.*' => ['distinct', Rule::in($available->all())],
            'revoke' => ['array'],
            'revoke.*' => ['distinct', Rule::in($revocable->values()->all())],
        ];
    }

    /**
     * Принимаемые согласия.
     *
     * @return Collection<int, ConsentType>
     */
    public function accepted(): Collection
    {
        return ConsentType::fromValues($this->collect('accept'));
    }

    /**
     * Отзываемые согласия.
     *
     * @return Collection<int, ConsentType>
     */
    public function revoked(): Collection
    {
        return ConsentType::fromValues($this->collect('revoke'));
    }
}
