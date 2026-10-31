{{--
    Иконка-ссылка в строке таблицы (например, редактирование записи).
    С $external ссылка открывает страницу сайта в новой вкладке.
--}}
<a class="article-admin__icon"
    href="{{ $href }}"
    @isset($external) target="_blank" rel="noopener" @endisset
    aria-label="{{ $label }}"
    title="{{ $title }}"><x-moonshine::icon :icon="$icon" /></a>
