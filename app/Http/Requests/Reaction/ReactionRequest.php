<?php

declare(strict_types=1);

namespace App\Http\Requests\Reaction;

use App\Enums\Reaction\ReactionType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Реакция на статью или комментарий: тип из адреса и признак постановки (PUT) или снятия (DELETE).
 */
final class ReactionRequest extends FormRequest
{
    /**
     * Полей у запроса нет.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Тип реакции из адреса.
     */
    public function type(): ReactionType
    {
        return ReactionType::from((string) $this->route('type'));
    }

    /**
     * Признак постановки: PUT ставит реакцию, DELETE снимает её.
     */
    public function on(): bool
    {
        return $this->isMethod('PUT');
    }
}
