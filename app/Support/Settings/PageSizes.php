<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsService;

/**
 * Размеры страниц списков из настроек сайта (группа «Списки»).
 */
final class PageSizes
{
    /**
     * @param SettingsService $settings настройки сайта
     */
    public function __construct(
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * Статей на странице ленты, автора и «Моих статей».
     */
    public function articles(): int
    {
        return $this->settings->integer(SettingKey::PaginationArticles);
    }

    /**
     * Веток комментариев и ответов за одну подгрузку.
     */
    public function comments(): int
    {
        return $this->settings->integer(SettingKey::PaginationComments);
    }

    /**
     * Альбомов на странице «Мои альбомы».
     */
    public function albums(): int
    {
        return $this->settings->integer(SettingKey::PaginationAlbums);
    }

    /**
     * Проголосовавших за одну подгрузку полного списка.
     */
    public function pollVoters(): int
    {
        return $this->settings->integer(SettingKey::PaginationPollVoters);
    }

    /**
     * Строк в списках админки.
     */
    public function admin(): int
    {
        return $this->settings->integer(SettingKey::PaginationAdmin);
    }

    /**
     * Строк в рейтингах и топах админки.
     */
    public function rating(): int
    {
        return $this->settings->integer(SettingKey::PaginationRating);
    }

    /**
     * Строк в топах страницы статистики.
     */
    public function statisticsTop(): int
    {
        return $this->settings->integer(SettingKey::PaginationStatisticsTop);
    }

    /**
     * Авторов в топе на главной странице админки.
     */
    public function topAuthors(): int
    {
        return $this->settings->integer(SettingKey::PaginationTopAuthors);
    }

    /**
     * Подсказок в поле тегов.
     */
    public function tagSuggestions(): int
    {
        return $this->settings->integer(SettingKey::PaginationTagSuggestions);
    }

    /**
     * Статей в RSS-ленте.
     */
    public function feed(): int
    {
        return $this->settings->integer(SettingKey::PaginationFeed);
    }
}
