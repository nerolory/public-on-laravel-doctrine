<?php

declare(strict_types=1);

namespace App\DTO\Settings;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Лимиты форм сайта и админки: публикации и поля сущностей.
 *
 * @implements Arrayable<string, int>
 */
final readonly class FormLimitsData implements Arrayable
{
    /**
     * @param int $articleTitle     длина заголовка статьи
     * @param int $summary          длина краткого описания статьи
     * @param int $images           картинок в тексте статьи
     * @param int $imageAlt         длина описания картинки
     * @param int $tags             тегов у статьи
     * @param int $tagLength        длина тега
     * @param int $pollQuestion     длина вопроса опроса
     * @param int $pollOptions      вариантов ответа в опросе
     * @param int $pollOptionLength длина варианта ответа
     * @param int $chartLabels      строк в таблице графика
     * @param int $chartSeries      рядов в таблице графика
     * @param int $chartTitle       длина заголовка графика
     * @param int $chartLabel       длина подписи строки или ряда графика
     * @param int $albumTitle       длина названия альбома
     * @param int $albumPhotos      фотографий в альбоме
     * @param int $commentLength    длина комментария
     * @param int $complaintText    длина текста жалобы
     * @param int $reason           длина причины отклонения или снятия
     * @param int $personName       длина фамилии, имени и отчества
     * @param int $email            длина адреса почты
     * @param int $password         минимальная длина пароля
     * @param int $socialUrl        длина ссылки на страницу в соцсети
     * @param int $phone            длина телефона
     * @param int $about            длина текста «О себе»
     */
    public function __construct(
        public int $articleTitle,
        public int $summary,
        public int $images,
        public int $imageAlt,
        public int $tags,
        public int $tagLength,
        public int $pollQuestion,
        public int $pollOptions,
        public int $pollOptionLength,
        public int $chartLabels,
        public int $chartSeries,
        public int $chartTitle,
        public int $chartLabel,
        public int $albumTitle,
        public int $albumPhotos,
        public int $commentLength,
        public int $complaintText,
        public int $reason,
        public int $personName,
        public int $email,
        public int $password,
        public int $socialUrl,
        public int $phone,
        public int $about,
    ) {
    }

    /**
     * Лимиты для фронтенда и атрибута data-limits редактора в админке.
     *
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'article_title' => $this->articleTitle,
            'summary' => $this->summary,
            'images' => $this->images,
            'image_alt' => $this->imageAlt,
            'tags' => $this->tags,
            'tag_length' => $this->tagLength,
            'poll_question' => $this->pollQuestion,
            'poll_options' => $this->pollOptions,
            'poll_option_length' => $this->pollOptionLength,
            'chart_labels' => $this->chartLabels,
            'chart_series' => $this->chartSeries,
            'chart_title' => $this->chartTitle,
            'chart_label' => $this->chartLabel,
            'album_title' => $this->albumTitle,
            'album_photos' => $this->albumPhotos,
            'comment_length' => $this->commentLength,
            'complaint_text' => $this->complaintText,
            'reason' => $this->reason,
            'person_name' => $this->personName,
            'email' => $this->email,
            'password' => $this->password,
            'social_url' => $this->socialUrl,
            'phone' => $this->phone,
            'about' => $this->about,
        ];
    }
}
