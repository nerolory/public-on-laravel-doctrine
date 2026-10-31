<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\DTO\Seo\AlternateUrlData;
use App\DTO\Seo\HeadData;
use App\DTO\Seo\LinkTagData;
use App\DTO\Seo\MetaTagData;
use App\DTO\Seo\PageSeoData;
use App\Entities\Article;
use App\Entities\ArticleSection;
use App\Entities\User;
use App\Enums\Settings\SettingKey;
use App\Services\Article\ArticleSectionService;
use App\Services\Media\ImageService;
use App\Services\Settings\SettingsService;
use App\Support\Seo\SiteUrls;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use Spatie\SchemaOrg\BlogPosting;
use Spatie\SchemaOrg\Organization;
use Spatie\SchemaOrg\Schema;

/**
 * Собирает содержимое <head> на сервере: title, description, canonical, keywords, Open Graph, Twitter Cards,
 * hreflang, коды подтверждения поисковиков, robots и JSON-LD. Фронтенд только выводит готовые теги.
 */
final class SeoService
{
    /** Ширина обложки для соцсетей: крупное превью в Open Graph и Twitter. */
    private const int COVER_WIDTH = 1440;

    /** Длина заголовка в разметке BlogPosting: поисковики показывают не больше 110 символов. */
    private const int HEADLINE_LENGTH = 110;

    /** Значение robots для страниц, закрытых от индексации. */
    private const string NOINDEX = 'noindex, nofollow';

    /** MIME-тип ленты RSS в теге link. */
    private const string RSS_TYPE = 'application/rss+xml';

    /** Коды подтверждения поисковиков: имя тега meta => настройка с кодом. */
    private const array VERIFICATIONS = [
        'yandex-verification' => SettingKey::SeoVerificationYandex,
        'google-site-verification' => SettingKey::SeoVerificationGoogle,
    ];

    /**
     * @param SettingsService       $settings    настройки SEO
     * @param SiteUrls              $urls        абсолютные адреса
     * @param ImageService          $images      адреса обложек
     * @param Translator            $translator  переводы и язык страницы
     * @param array<string, string> $territories страна og:locale по коду языка из config/seo.php
     * @param ArticleSectionService $sections    разделы для тегов страницы раздела
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SiteUrls $urls,
        private readonly ImageService $images,
        private readonly Translator $translator,
        private readonly array $territories,
        private readonly ArticleSectionService $sections,
    ) {
    }

    /**
     * Теги статьи: SEO-поля модератора или заголовок и анонс, теги и разделы, разметка BlogPosting.
     *
     * @param Collection<int, string> $tags     названия тегов статьи
     * @param Collection<int, string> $sections названия разделов статьи
     */
    public function article(Article $article, ?User $author, Collection $tags, Collection $sections): HeadData
    {
        $path = SiteUrls::articlePath($article->persistedId());
        $description = $article->getSeo()->description() ?? $article->excerpt();
        $cover = $article->getCover();
        $image = $cover === null
            ? $this->defaultImage()
            : $this->absolute($this->images->present($cover, self::COVER_WIDTH)->url);
        $published = $article->getPublishedAt()?->format(DATE_ATOM);
        $modified = $article->getUpdatedAt()->format(DATE_ATOM);
        $schema = $this->articleSchema($article, $author, $path, $description, $tags, $sections)
            ->dateModified($modified);

        if ($published !== null) {
            $schema->datePublished($published);
        }

        if ($image !== null) {
            $schema->image($image);
        }

        return $this->head(new PageSeoData(
            title: $article->getSeo()->title() ?? $article->getTitle() . ' — ' . $this->settings->siteName(),
            path: $path,
            description: $description,
            ogType: 'article',
            image: $image,
            jsonLd: collect([$schema]),
            ogTitle: $article->getTitle(),
            meta: $tags->isEmpty()
                ? collect()
                : collect([new MetaTagData(content: $tags->implode(', '), name: 'keywords')]),
            ogTags: self::articleMeta($published, $modified, $tags, $sections),
            noindex: ! $article->isPublished(),
        ));
    }

    /**
     * Теги главной: заголовок и описание из настроек, разметка WebSite и Organization.
     */
    public function home(): HeadData
    {
        $site = $this->settings->siteName();

        return $this->head(new PageSeoData(
            // Без своего заголовка главная называется как сайт.
            title: $this->settings->optionalString(SettingKey::SeoHomeTitle) ?? $site,
            path: '/',
            description: $this->settings->optionalString(SettingKey::SeoHomeDescription),
            ogType: 'website',
            image: $this->defaultImage(),
            jsonLd: collect([Schema::webSite()->name($site)->url($this->urls->to('/')), $this->organization()]),
        ));
    }

    /**
     * Теги главной или раздела по slug из фильтра; без slug — главная.
     */
    public function homeOrSection(?string $slug): HeadData
    {
        if ($slug === null) {
            return $this->home();
        }

        $section = $this->sections->getBySlug($slug);

        return $this->section($section, $this->sections->path($section));
    }

    /**
     * Теги раздела (главная с фильтром): SEO-поля раздела или шаблон из переводов, хлебные крошки.
     *
     * @param Collection<int, ArticleSection> $trail путь от корня до раздела
     */
    public function section(ArticleSection $section, Collection $trail): HeadData
    {
        $site = $this->settings->siteName();
        $name = $section->getName();
        // Первая крошка — главная, разделы идут с позиции 2.
        $crumbs = $trail->values()->map(fn (ArticleSection $node, int $index): mixed => Schema::listItem()
            ->position($index + 2)
            ->name($node->getName())
            ->item($this->urls->to(SiteUrls::sectionPath($node->getSlug()))));

        return $this->head(new PageSeoData(
            title: $section->getSeo()->title()
                ?? $this->translator->get('messages.seo.section_title', ['name' => $name, 'site' => $site]),
            path: SiteUrls::sectionPath($section->getSlug()),
            description: $section->getSeo()->description()
                ?? $this->translator->get('messages.seo.section_description', ['name' => $name]),
            ogType: 'website',
            image: $this->defaultImage(),
            jsonLd: collect([Schema::breadcrumbList()->itemListElement([
                Schema::listItem()->position(1)->name($site)->item($this->urls->to('/')),
                ...$crumbs,
            ])]),
        ));
    }

    /**
     * Разметка BlogPosting без дат и картинки.
     *
     * @param Collection<int, string> $tags
     * @param Collection<int, string> $sections
     */
    private function articleSchema(
        Article $article,
        ?User $author,
        string $path,
        string $description,
        Collection $tags,
        Collection $sections,
    ): BlogPosting {
        $schema = Schema::blogPosting()
            ->headline(mb_substr($article->getTitle(), 0, self::HEADLINE_LENGTH))
            ->description($description)
            ->mainEntityOfPage($this->urls->to($path))
            ->publisher($this->organization())
            ->keywords($tags->all())
            ->articleSection($sections->all());

        if ($author !== null) {
            $schema->author(Schema::person()
                ->name($author->publicName())
                ->url($this->urls->to(SiteUrls::authorPath($author->persistedId()))));
        }

        return $schema;
    }

    /**
     * Метки статьи для Open Graph: время публикации и изменения, теги и разделы.
     *
     * @param Collection<int, string> $tags
     * @param Collection<int, string> $sections
     *
     * @return Collection<int, MetaTagData>
     */
    private static function articleMeta(
        ?string $published,
        string $modified,
        Collection $tags,
        Collection $sections,
    ): Collection {
        return collect([
            $published === null ? null : new MetaTagData(content: $published, property: 'article:published_time'),
            new MetaTagData(content: $modified, property: 'article:modified_time'),
        ])
            ->filter()
            ->merge($tags->map(
                static fn (string $tag): MetaTagData => new MetaTagData(content: $tag, property: 'article:tag'),
            ))
            ->merge($sections->map(
                static fn (string $section): MetaTagData => new MetaTagData(
                    content: $section,
                    property: 'article:section',
                ),
            ));
    }

    /**
     * Разметка Organization: сайт как издатель.
     */
    private function organization(): Organization
    {
        return Schema::organization()->name($this->settings->siteName())->url($this->urls->to('/'));
    }

    /**
     * Все теги страницы: описание, robots, подтверждения поисковиков, Open Graph, Twitter и ссылки.
     */
    private function head(PageSeoData $page): HeadData
    {
        $meta = self::filled(collect([self::meta($page->description, name: 'description')]))
            ->merge($page->meta)
            ->merge($this->robots($page->noindex))
            ->merge($this->verifications())
            ->merge($this->openGraph($page))
            ->merge($this->twitter($page));

        return new HeadData($page->title, $meta->values(), $this->links($page->path), $page->jsonLd);
    }

    /**
     * Запрет индексации: для всего сайта из настроек или для неопубликованной страницы.
     *
     * @return Collection<int, MetaTagData>
     */
    private function robots(bool $noindex): Collection
    {
        if (! $noindex && ! $this->settings->boolean(SettingKey::SeoNoindex)) {
            return collect();
        }

        return collect([new MetaTagData(content: self::NOINDEX, name: 'robots')]);
    }

    /**
     * Коды подтверждения прав на сайт для поисковиков; незаполненные не выводятся.
     *
     * @return Collection<int, MetaTagData>
     */
    private function verifications(): Collection
    {
        return self::filled(collect(self::VERIFICATIONS)->map(
            fn (SettingKey $key, string $name): ?MetaTagData => self::meta(
                $this->settings->optionalString($key),
                name: $name,
            ),
        ));
    }

    /**
     * Свойства Open Graph; пустые значения не выводятся.
     *
     * @return Collection<int, MetaTagData>
     */
    private function openGraph(PageSeoData $page): Collection
    {
        if (! $this->settings->boolean(SettingKey::SeoOgEnabled)) {
            return collect();
        }

        $locale = $this->locale();

        return self::filled(collect([
            new MetaTagData(content: $page->ogType, property: 'og:type'),
            new MetaTagData(content: $page->ogTitle ?? $page->title, property: 'og:title'),
            self::meta($page->description, property: 'og:description'),
            new MetaTagData(content: $this->urls->to($page->path), property: 'og:url'),
            new MetaTagData(content: $this->settings->siteName(), property: 'og:site_name'),
            self::meta($locale, property: 'og:locale'),
            self::meta($page->image, property: 'og:image'),
        ])->merge($page->ogTags));
    }

    /**
     * Карточка Twitter: крупная картинка, если она есть; пустые значения не выводятся.
     *
     * @return Collection<int, MetaTagData>
     */
    private function twitter(PageSeoData $page): Collection
    {
        if (! $this->settings->boolean(SettingKey::SeoTwitterEnabled)) {
            return collect();
        }

        return self::filled(collect([
            new MetaTagData(content: $page->image === null ? 'summary' : 'summary_large_image', name: 'twitter:card'),
            self::meta($this->settings->optionalString(SettingKey::SeoTwitterSite), name: 'twitter:site'),
            new MetaTagData(content: $page->ogTitle ?? $page->title, name: 'twitter:title'),
            self::meta($page->description, name: 'twitter:description'),
            self::meta($page->image, name: 'twitter:image'),
        ]));
    }

    /**
     * Тег meta для необязательного значения: без значения тега нет.
     */
    private static function meta(?string $content, ?string $name = null, ?string $property = null): ?MetaTagData
    {
        return $content === null ? null : new MetaTagData(content: $content, name: $name, property: $property);
    }

    /**
     * Теги со значением: незаполненные (null) и пустые не выводятся.
     *
     * @param Collection<array-key, MetaTagData|null> $tags
     *
     * @return Collection<int, MetaTagData>
     */
    private static function filled(Collection $tags): Collection
    {
        return $tags
            ->filter(static fn (?MetaTagData $tag): bool => $tag !== null && $tag->content !== '')
            ->values();
    }

    /**
     * Канонический адрес, языковые версии и RSS.
     *
     * @return Collection<int, LinkTagData>
     */
    private function links(string $path): Collection
    {
        $links = collect([new LinkTagData(rel: 'canonical', href: $this->urls->to($path))])
            ->merge($this->urls->alternates($path)->map(
                static fn (AlternateUrlData $alternate): LinkTagData => new LinkTagData(
                    rel: 'alternate',
                    href: $alternate->href,
                    hreflang: $alternate->hreflang,
                ),
            ));

        if ($this->settings->boolean(SettingKey::SeoRssEnabled)) {
            $links->push(new LinkTagData(
                rel: 'alternate',
                href: $this->urls->to(SiteUrls::RSS_PATH),
                type: self::RSS_TYPE,
                title: $this->settings->siteName(),
            ));
        }

        return $links;
    }

    /**
     * Картинка для соцсетей по умолчанию из настроек.
     */
    private function defaultImage(): ?string
    {
        return $this->settings->optionalString(SettingKey::SeoDefaultImage);
    }

    /**
     * Адреса картинок из локального хранилища относительные; соцсетям нужны полные.
     */
    private function absolute(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : $this->urls->base() . $url;
    }

    /**
     * Язык Open Graph в виде язык_СТРАНА: ru_RU, en_US. Страна берётся из конфигурации;
     * для языка без страны в конфигурации тега нет.
     */
    private function locale(): ?string
    {
        $code = $this->translator->getLocale();
        $territory = $this->territories[$code] ?? null;

        return $territory === null ? null : $code . '_' . $territory;
    }
}
