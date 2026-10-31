<?php

declare(strict_types=1);

namespace App\Http\Requests\Common;

use App\Traits\ReadsPage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Номер страницы списка из параметра ?page=.
 */
class PageRequest extends FormRequest
{
    use ReadsPage;

    /**
     * Правила проверки номера страницы.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->pageRules();
    }
}
