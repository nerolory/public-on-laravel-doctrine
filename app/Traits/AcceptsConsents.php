<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Legal\ConsentType;
use App\Services\Legal\ConsentService;
use Illuminate\Support\Collection;

/**
 * Согласия в формах регистрации: обязательные задаёт политика региона, остальные — по желанию.
 */
trait AcceptsConsents
{
    /**
     * Сообщение для неотмеченного обязательного согласия.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['consents.*.accepted' => __('legal.required_consent')];
    }

    /**
     * Согласия, которые пользователь отметил.
     *
     * @return Collection<int, ConsentType>
     */
    public function acceptedConsents(): Collection
    {
        return $this->safe()->collect('consents')
            ->filter(static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->map(static fn (int|string $type): ConsentType => ConsentType::from((string) $type))
            ->values();
    }

    /**
     * Правила поля consents: обязательные согласия должны быть приняты.
     *
     * @return array<string, mixed>
     */
    protected function consentRules(ConsentService $consents): array
    {
        return $consents->requiredTypes()
            ->mapWithKeys(static fn (ConsentType $type): array => ['consents.' . $type->value => ['accepted']])
            ->merge([
                // Принимаются только согласия, документы которых опубликованы в регионе.
                'consents' => ['array:' . $consents->availableTypes()->implode(',')],
                'consents.*' => ['boolean'],
            ])
            ->all();
    }
}
