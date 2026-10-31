<?php

declare(strict_types=1);

namespace App\Traits;

use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Fields\Hidden;

/**
 * Общие части форм админки: кнопка отправки основного действия и скрытый метод PUT для правки.
 */
trait BuildsAdminForms
{
    /** Класс основной кнопки отправки: выделяет главное действие формы среди остальных элементов. */
    private const string SUBMIT_CLASS = 'btn-primary';

    /**
     * Форма с кнопкой отправки основного действия.
     */
    protected function adminSubmit(FormBuilder $form, string $label): FormBuilder
    {
        return $form->submit($label, ['class' => self::SUBMIT_CLASS]);
    }

    /**
     * Скрытое поле _method=PUT: правка отправляет форму методом PUT.
     */
    protected function adminPutMethodField(): Hidden
    {
        return Hidden::make('_method')->setValue('PUT');
    }
}
