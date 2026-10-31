<?php

declare(strict_types=1);

namespace App\Services\Article;

use App\DTO\Media\ChartData;
use App\DTO\Media\ImageData;
use App\Entities\Album;
use App\Entities\Chart;
use App\Entities\Image;
use App\Entities\User;
use App\Enums\Album\AlbumLayout;
use App\Enums\Media\ImagePurpose;
use App\Repositories\AlbumRepository;
use App\Repositories\ChartRepository;
use App\Repositories\ImageRepository;
use App\Services\Media\ImageService;
use App\Traits\WorksWithHtmlFragments;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * HTML статьи из редактора. В базе хранится канонический вид: разрешённые теги, картинки — ссылками data-image,
 * графики и альбомы — блоками figure со ссылками data-chart и data-album на UUID. Адреса и размеры картинок,
 * таблица данных графика и фото альбома подставляются при показе, поэтому смена хранилища не ломает старые статьи.
 */
final class ArticleContent
{
    use WorksWithHtmlFragments;

    /** Наибольшая длина HTML статьи в символах: ограничивает размер текста в базе и время очистки. */
    public const int MAX_LENGTH = 200000;

    /** Метка вставок: сбрасывается при любом изменении альбомов, и HTML статей перестраивается. */
    public const string EMBEDS_KEY = 'article-content-embeds';

    /** Срок жизни готового HTML в кеше, секунды: сутки. */
    private const int CACHE_TTL = 86400;

    /** Длина случайной метки вставок в байтах: метка входит в ключ кеша, 4 байта дают 8 hex-символов. */
    private const int EMBEDS_BYTES = 4;

    /** Атрибут sizes картинки в тексте: ширина колонки чтения $width-reading из SCSS фронтенда, 800px. */
    private const string IMAGE_SIZES = '(max-width: 800px) 100vw, 800px';

    /** Блок альбома в тексте. */
    private const string ALBUM_SELECTOR = 'figure[data-album]';

    /** Атрибут блока альбома с его UUID. */
    private const string ALBUM_ATTRIBUTE = 'data-album';

    /** Блок графика в тексте. */
    private const string CHART_SELECTOR = 'figure[data-chart]';

    /** Атрибут блока графика с его UUID. */
    private const string CHART_ATTRIBUTE = 'data-chart';

    /** Картинка в тексте, загруженная через хранилище. */
    private const string IMAGE_SELECTOR = 'img[data-image]';

    /** Атрибут картинки с её UUID. */
    private const string IMAGE_ATTRIBUTE = 'data-image';

    /** Теги, которые остаются в тексте без атрибутов. */
    private const array ALLOWED_ELEMENTS = [
        'p', 'h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'code', 'pre', 'blockquote', 'ul', 'li', 'br', 'hr',
        'table', 'thead', 'tbody', 'tr', 'figcaption',
    ];

    /** Обёртки из вставленного извне текста: снимаются, содержимое остаётся. */
    private const array UNWRAPPED_ELEMENTS = [
        'div', 'span', 'section', 'article', 'header', 'footer', 'main', 'h1', 'h5', 'h6',
        'font', 'small', 'sub', 'sup', 'mark', 'b', 'i',
    ];

    /**
     * @param ImageRepository $images       картинки
     * @param ImageService    $imageService адреса и размеры картинок
     * @param ChartRepository $charts       графики
     * @param AlbumRepository $albums       альбомы
     * @param Cache           $cache        кеш готового HTML
     * @param HtmlSanitizer   $sanitizer    очистка HTML по настройкам {@see self::sanitizerConfig()}
     */
    public function __construct(
        private readonly ImageRepository $images,
        private readonly ImageService $imageService,
        private readonly ChartRepository $charts,
        private readonly AlbumRepository $albums,
        private readonly Cache $cache,
        private readonly HtmlSanitizer $sanitizer,
    ) {
    }

    /**
     * Пустой текст редактора — это «<p></p>»: ни слов, ни картинок, ни графиков.
     */
    public static function isEmpty(string $html): bool
    {
        $body = self::parse($html)->body;

        return trim(str_replace("\u{00A0}", ' ', $body->textContent)) === ''
            && $body->querySelector(
                implode(', ', [self::IMAGE_SELECTOR, self::CHART_SELECTOR, self::ALBUM_SELECTOR]),
            ) === null;
    }

    /**
     * Сколько картинок в тексте: для проверки лимита до сохранения.
     */
    public static function countImages(string $html): int
    {
        return self::parse($html)->body->querySelectorAll(self::IMAGE_SELECTOR)->length;
    }

    /**
     * UUID графиков в тексте статьи: по ним фронтенд получает данные для отрисовки.
     *
     * @return Collection<int, string>
     */
    public static function chartUuids(string $html): Collection
    {
        return self::uuids(self::parse($html), self::CHART_SELECTOR, self::CHART_ATTRIBUTE);
    }

    /**
     * Настройки санитайзера: разрешённые теги, атрибуты и схемы ссылок. Санитайзер создаёт провайдер.
     */
    public static function sanitizerConfig(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow ugc')
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::ALLOWED_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::UNWRAPPED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        return $config
            ->allowElement('ol', ['start'])
            ->allowElement('a', ['href'])
            ->allowElement('th', ['colspan', 'rowspan'])
            ->allowElement('td', ['colspan', 'rowspan'])
            ->allowElement('figure', ['data-type', self::ALBUM_ATTRIBUTE, 'data-layout', self::CHART_ATTRIBUTE])
            ->allowElement('img', ['alt', self::IMAGE_ATTRIBUTE]);
    }

    /**
     * Очищает HTML автора: лишние теги и атрибуты убирает санитайзер, а альбомы, картинки и графики остаются,
     * только если их загрузил автор статьи или тот, кто её правит.
     *
     * @param Collection<int, User> $owners автор и редактор
     */
    public function prepare(string $html, Collection $owners): string
    {
        $document = self::parse($this->sanitizer->sanitize($html));
        $this->keepOwnAlbums($document, $owners);
        $this->keepOwnImages($document, $owners);
        $this->keepOwnCharts($document, $owners);

        return self::serialize($document);
    }

    /**
     * HTML для показа: адреса и размеры картинок, таблицы данных графиков, фото опубликованных альбомов.
     */
    public function render(string $html): string
    {
        $document = self::parse($html);
        $this->renderAlbums($document);
        $this->renderImages($document);
        $this->renderCharts($document);

        return self::serialize($document);
    }

    /**
     * HTML для редактора: альбомы и графики остаются блоками с подписью, без фото и таблиц данных.
     * Альбом показан даже пока не прошёл модерацию, так он сохранится при правке.
     */
    public function renderForEditor(string $html): string
    {
        $document = self::parse($html);
        $this->captionAlbums($document);
        $this->renderImages($document);
        $this->captionCharts($document);

        return self::serialize($document);
    }

    /**
     * Готовый HTML статьи кешируется по номеру версии: любая правка меняет ключ.
     */
    public function renderCached(int $articleId, int $version, string $html): string
    {
        $embeds = $this->cache->rememberForever(
            self::EMBEDS_KEY,
            static fn (): string => bin2hex(random_bytes(self::EMBEDS_BYTES)),
        );

        return $this->cache->remember(
            "article-html:{$articleId}:{$version}:{$embeds}",
            self::CACHE_TTL,
            fn (): string => $this->render($html),
        );
    }

    /**
     * Альбом хранится пустым блоком со ссылкой: фото и подпись подставляются при показе. Чужие альбомы
     * и альбомы с неизвестным видом показа убираются.
     *
     * @param Collection<int, User> $owners
     */
    private function keepOwnAlbums(HTMLDocument $document, Collection $owners): void
    {
        self::eachReferenced(
            $document,
            self::ALBUM_SELECTOR,
            self::ALBUM_ATTRIBUTE,
            $this->albumsByUuid($document),
            static function (Element $node, Album $album) use ($owners): void {
                $layout = AlbumLayout::tryFrom(self::attribute($node, 'data-layout'));
                $owned = $owners->contains(static fn (User $owner): bool => $album->isOwnedBy($owner));

                if ($layout === null || ! $owned) {
                    self::remove($node);

                    return;
                }

                $node->replaceChildren();
                $node->setAttribute('data-type', 'album');
                $node->setAttribute('data-layout', $layout->value);
            },
        );
    }

    /**
     * Оставляет картинки текста, загруженные автором или редактором.
     *
     * @param Collection<int, User> $owners
     */
    private function keepOwnImages(HTMLDocument $document, Collection $owners): void
    {
        $isOwner = static fn (?User $user): bool => $user !== null && $owners->contains(
            static fn (User $owner): bool => $owner->getAuthIdentifier() === $user->getAuthIdentifier(),
        );

        self::eachReferenced(
            $document,
            'img',
            self::IMAGE_ATTRIBUTE,
            $this->imagesByUuid($document),
            static function (Element $node, Image $image) use ($isOwner): void {
                if ($image->getPurpose() !== ImagePurpose::Content || ! $isOwner($image->getOwner())) {
                    self::remove($node);
                }
            },
        );
    }

    /**
     * График хранится блоком со ссылкой и подписью-заголовком. Чужие и неизвестные графики убираются.
     *
     * @param Collection<int, User> $owners
     */
    private function keepOwnCharts(HTMLDocument $document, Collection $owners): void
    {
        self::eachReferenced(
            $document,
            self::CHART_SELECTOR,
            self::CHART_ATTRIBUTE,
            $this->chartsByUuid($document),
            static function (Element $node, Chart $chart) use ($document, $owners): void {
                if (! $owners->contains(static fn (User $owner): bool => $chart->isOwnedBy($owner))) {
                    self::remove($node);

                    return;
                }

                $node->setAttribute('data-type', 'chart');
                $node->replaceChildren(self::caption($document, $chart->getTitle()));
            },
        );
    }

    /**
     * Альбомы для показа: фото опубликованного альбома; неопубликованные и неизвестные убираются.
     */
    private function renderAlbums(HTMLDocument $document): void
    {
        self::eachReferenced(
            $document,
            self::ALBUM_SELECTOR,
            self::ALBUM_ATTRIBUTE,
            $this->albumsByUuid($document),
            function (Element $node, Album $album) use ($document): void {
                if (! $album->isPublished()) {
                    self::remove($node);

                    return;
                }

                $this->renderAlbum($document, $node, $album);
            },
        );
    }

    /**
     * Альбомы для редактора: блок с названием, неизвестные убираются.
     */
    private function captionAlbums(HTMLDocument $document): void
    {
        self::eachReferenced(
            $document,
            self::ALBUM_SELECTOR,
            self::ALBUM_ATTRIBUTE,
            $this->albumsByUuid($document),
            static function (Element $node, Album $album) use ($document): void {
                $node->replaceChildren(self::caption($document, $album->getTitle()));
            },
        );
    }

    /**
     * Адреса и размеры картинок текста; фото альбомов к этому моменту уже готовы.
     */
    private function renderImages(HTMLDocument $document): void
    {
        self::eachReferenced(
            $document,
            self::IMAGE_SELECTOR,
            self::IMAGE_ATTRIBUTE,
            $this->imagesByUuid($document),
            function (Element $node, Image $image): void {
                $data = $this->imageService->present($image, ImagePurpose::PAGE_WIDTH);
                self::setAttributes($node, $this->imageAttributes($data, self::IMAGE_SIZES));
            },
        );
    }

    /**
     * Атрибуты картинки: адрес, набор ширин, размеры для разметки и отложенная загрузка.
     *
     * @return array<string, string>
     */
    private function imageAttributes(ImageData $data, string $sizes): array
    {
        return [
            'src' => $data->url,
            'srcset' => $data->srcset,
            'sizes' => $sizes,
            'width' => (string) $data->width,
            'height' => (string) $data->height,
            'loading' => 'lazy',
            'decoding' => 'async',
        ];
    }

    /**
     * Графики для показа: таблица данных и подпись (рисует их фронтенд); неизвестные убираются.
     */
    private function renderCharts(HTMLDocument $document): void
    {
        self::eachReferenced(
            $document,
            self::CHART_SELECTOR,
            self::CHART_ATTRIBUTE,
            $this->chartsByUuid($document),
            static function (Element $node, Chart $chart) use ($document): void {
                $caption = self::caption($document, $chart->getTitle());
                $node->replaceChildren(self::chartTable($document, $chart->chart()), $caption);
            },
        );
    }

    /**
     * Графики для редактора: только подпись, неизвестные убираются.
     */
    private function captionCharts(HTMLDocument $document): void
    {
        self::eachReferenced(
            $document,
            self::CHART_SELECTOR,
            self::CHART_ATTRIBUTE,
            $this->chartsByUuid($document),
            static function (Element $node, Chart $chart) use ($document): void {
                $node->replaceChildren(self::caption($document, $chart->getTitle()));
            },
        );
    }

    /**
     * Таблица данных графика: строки — подписи, столбцы — ряды. Её читают экранные дикторы и браузеры без скриптов.
     */
    private static function chartTable(HTMLDocument $document, ChartData $chart): Element
    {
        $table = $document->createElement('table');
        $table->setAttribute('class', 'chart-table');
        $head = $document->createElement('tr');
        $head->append(self::cell($document, 'td', ''));

        foreach ($chart->series as $series) {
            $head->append(self::cell($document, 'th', $series->name, 'col'));
        }

        $thead = $document->createElement('thead');
        $thead->append($head);
        $tbody = $document->createElement('tbody');

        foreach ($chart->labels->values() as $index => $label) {
            $row = $document->createElement('tr');
            $row->append(self::cell($document, 'th', $label, 'row'));

            foreach ($chart->series as $series) {
                $row->append(self::cell($document, 'td', (string) $series->valueAt($index)));
            }

            $tbody->append($row);
        }

        $table->append($thead, $tbody);

        return $table;
    }

    /**
     * Слайдер — горизонтальная лента с прокруткой (scroll-snap), коллаж — сетка. Обходятся без скриптов,
     * лента доступна с клавиатуры: у неё tabindex и подпись.
     */
    private function renderAlbum(HTMLDocument $document, Element $figure, Album $album): void
    {
        $layout = AlbumLayout::from(self::attribute($figure, 'data-layout'));
        $figure->setAttribute('class', 'album album-' . $layout->value);
        $track = $document->createElement('div');
        self::setAttributes($track, [
            'class' => 'album-track',
            'tabindex' => '0',
            'role' => 'region',
            'aria-label' => $album->getTitle(),
        ]);

        foreach ($album->getItems() as $item) {
            $data = $this->imageService->present($item->getImage(), ImagePurpose::PAGE_WIDTH);
            $image = $document->createElement('img');
            self::setAttributes($image, $this->imageAttributes($data, $layout->sizes()));
            // Без описания атрибут alt не выводится.
            $alt = $item->getAlt();
            if ($alt !== null) {
                $image->setAttribute('alt', $alt);
            }
            $track->append($image);
        }

        $figure->replaceChildren($track, self::caption($document, $album->getTitle()));
    }

    /**
     * Альбомы, на которые ссылается текст.
     *
     * @return Collection<string, Album> по UUID
     */
    private function albumsByUuid(HTMLDocument $document): Collection
    {
        $uuids = self::uuids($document, self::ALBUM_SELECTOR, self::ALBUM_ATTRIBUTE);

        return $this->albums->findByUuids($uuids)->keyBy(static fn (Album $album): string => $album->getUuid());
    }

    /**
     * Картинки, на которые ссылается текст.
     *
     * @return Collection<string, Image> по UUID
     */
    private function imagesByUuid(HTMLDocument $document): Collection
    {
        $uuids = self::uuids($document, self::IMAGE_SELECTOR, self::IMAGE_ATTRIBUTE);

        return $this->images->findByUuids($uuids);
    }

    /**
     * Графики, на которые ссылается текст.
     *
     * @return Collection<string, Chart> по UUID
     */
    private function chartsByUuid(HTMLDocument $document): Collection
    {
        $uuids = self::uuids($document, self::CHART_SELECTOR, self::CHART_ATTRIBUTE);

        return $this->charts->findByUuids($uuids);
    }
}
