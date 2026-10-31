<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\DTO\Settings\FormLimitsData;
use App\Entities\Album;
use App\Entities\AlbumItem;
use App\Entities\Article;
use App\Entities\Chart;
use App\Entities\Complaint;
use App\Entities\PersonName;
use App\Entities\Poll;
use App\Entities\PollOption;
use App\Entities\Tag;
use App\Entities\User;
use App\Enums\Moderation\ModerationStatus;
use App\Enums\Settings\SettingKey;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Settings\SettingsService;

/**
 * Лимиты публикаций из настроек сайта (группа «Публикации»).
 */
final class ContentLimits
{
    /**
     * @param SettingsService $settings настройки сайта
     */
    public function __construct(
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * Картинок в тексте одной статьи.
     */
    public function articleImages(): int
    {
        return $this->settings->integer(SettingKey::ContentArticleImages);
    }

    /**
     * Тегов у одной статьи.
     */
    public function articleTags(): int
    {
        return $this->settings->integer(SettingKey::ContentArticleTags);
    }

    /**
     * Фотографий в одном альбоме.
     */
    public function albumPhotos(): int
    {
        return $this->settings->integer(SettingKey::ContentAlbumPhotos);
    }

    /**
     * Символов в комментарии.
     */
    public function commentLength(): int
    {
        return $this->settings->integer(SettingKey::ContentCommentLength);
    }

    /**
     * Подписей по оси в таблице графика.
     */
    public function chartLabels(): int
    {
        return $this->settings->integer(SettingKey::ContentChartLabels);
    }

    /**
     * Рядов значений в таблице графика.
     */
    public function chartSeries(): int
    {
        return $this->settings->integer(SettingKey::ContentChartSeries);
    }

    /**
     * Вариантов ответа в опросе.
     */
    public function pollOptions(): int
    {
        return $this->settings->integer(SettingKey::ContentPollMaxOptions);
    }

    /**
     * Аватаров проголосовавших у каждого варианта неанонимного опроса.
     */
    public function pollPreviewVoters(): int
    {
        return $this->settings->integer(SettingKey::ContentPollPreviewVoters);
    }

    /**
     * Лимиты всех форм: настройки сайта и длины полей сущностей.
     */
    public function forms(): FormLimitsData
    {
        return new FormLimitsData(
            articleTitle: Article::TITLE_LENGTH,
            summary: Article::SUMMARY_LENGTH,
            images: $this->articleImages(),
            imageAlt: AlbumItem::ALT_LENGTH,
            tags: $this->articleTags(),
            tagLength: Tag::MAX_LENGTH,
            pollQuestion: Poll::QUESTION_LENGTH,
            pollOptions: $this->pollOptions(),
            pollOptionLength: PollOption::TEXT_LENGTH,
            chartLabels: $this->chartLabels(),
            chartSeries: $this->chartSeries(),
            chartTitle: Chart::TITLE_LENGTH,
            chartLabel: Chart::LABEL_LENGTH,
            albumTitle: Album::TITLE_LENGTH,
            albumPhotos: $this->albumPhotos(),
            commentLength: $this->commentLength(),
            complaintText: Complaint::TEXT_LENGTH,
            reason: ModerationStatus::REASON_LENGTH,
            personName: PersonName::PART_LENGTH,
            email: User::EMAIL_LENGTH,
            password: RegisterRequest::PASSWORD_MIN_LENGTH,
            socialUrl: User::SOCIAL_URL_LENGTH,
            phone: User::PHONE_LENGTH,
            about: User::ABOUT_LENGTH,
        );
    }
}
