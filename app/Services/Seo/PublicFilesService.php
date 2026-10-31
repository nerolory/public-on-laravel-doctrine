<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\DTO\Seo\AlternateUrlData;
use App\Entities\Article;
use App\Enums\Settings\SettingKey;
use App\Repositories\AlbumRepository;
use App\Repositories\ArticleRepository;
use App\Repositories\ArticleSectionRepository;
use App\Services\Settings\SettingsService;
use App\Support\Seo\SiteUrls;
use App\Support\Settings\PageSizes;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Collection;
use LogicException;
use Spatie\Feed\Feed;
use Spatie\Feed\FeedItem;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * robots.txt, карта сайта и RSS: всё строится из настроек и опубликованных материалов.
 */
final class PublicFilesService
{
    /** Служебные разделы сайта, закрытые от поисковиков. */
    private const array PRIVATE_PATHS = [
        '/admin',
        '/api',
        '/account',
        '/login',
        '/register',
        '/write',
        '/my-articles',
        '/my-albums',
    ];

    /**
     * @param SettingsService          $settings    настройки SEO и RSS
     * @param SiteUrls                 $urls        абсолютные адреса
     * @param ArticleRepository        $articles    статьи
     * @param ArticleSectionRepository $sections    разделы
     * @param AlbumRepository          $albums      альбомы
     * @param PageSizes                $pageSizes   размер RSS-ленты
     * @param Translator               $translator  язык ленты
     * @param string                   $sitemapPath путь к файлу карты сайта
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly SiteUrls $urls,
        private readonly ArticleRepository $articles,
        private readonly ArticleSectionRepository $sections,
        private readonly AlbumRepository $albums,
        private readonly PageSizes $pageSizes,
        private readonly Translator $translator,
        private readonly string $sitemapPath,
    ) {
    }

    /**
     * Текст robots.txt: при закрытом сайте запрещено всё, иначе служебные разделы, дополнения и карта сайта.
     */
    public function robots(): string
    {
        if ($this->settings->boolean(SettingKey::SeoNoindex)) {
            return "User-agent: *\nDisallow: /\n";
        }

        $extra = $this->settings->optionalString(SettingKey::SeoRobotsExtra);
        $lines = collect(['User-agent: *'])
            ->merge(array_map(static fn (string $path): string => 'Disallow: ' . $path, self::PRIVATE_PATHS))
            ->when($extra !== null, static fn (Collection $lines): Collection => $lines->push($extra))
            ->push('')
            ->push('Sitemap: ' . $this->urls->to('/sitemap.xml'));

        return $lines->implode("\n") . "\n";
    }

    /**
     * Пересобирает карту сайта: главная, разделы, опубликованные статьи и альбомы, с языковыми версиями.
     *
     * @return int число адресов в карте
     */
    public function generateSitemap(): int
    {
        $sitemap = Sitemap::create();
        $this->addUrl($sitemap, '/');

        foreach ($this->sections->all() as $section) {
            $this->addUrl($sitemap, SiteUrls::sectionPath($section->slug));
        }

        foreach ($this->articles->publishedForSitemap() as $id => $updatedAt) {
            $this->addUrl($sitemap, SiteUrls::articlePath($id), $updatedAt);
        }

        foreach ($this->albums->publishedForSitemap() as $uuid => $updatedAt) {
            $this->addUrl($sitemap, SiteUrls::albumPath($uuid), $updatedAt);
        }

        $sitemap->writeToFile($this->sitemapPath);

        return count($sitemap->getTags());
    }

    /**
     * Путь к готовой карте сайта; если её ещё нет, она строится сразу.
     */
    public function sitemapFile(): string
    {
        if (! is_file($this->sitemapPath)) {
            $this->generateSitemap();
        }

        return $this->sitemapPath;
    }

    /**
     * Включена ли RSS-лента.
     */
    public function feedEnabled(): bool
    {
        return $this->settings->boolean(SettingKey::SeoRssEnabled);
    }

    /**
     * RSS-лента последних опубликованных статей.
     */
    public function feed(): Feed
    {
        return new Feed(
            title: $this->settings->siteName(),
            items: $this->feedItems(),
            url: $this->feedUrl(),
            view: 'feed::rss',
            language: str_replace('_', '-', $this->translator->getLocale()),
            format: 'rss',
        );
    }

    /**
     * Адрес ленты.
     */
    private function feedUrl(): string
    {
        return $this->urls->to(SiteUrls::RSS_PATH);
    }

    /**
     * Последние опубликованные статьи для RSS.
     *
     * @return Collection<int, FeedItem>
     */
    private function feedItems(): Collection
    {
        return $this->articles->latestPublished($this->pageSizes->feed())->map(function (Article $article): FeedItem {
            $link = $this->urls->to(SiteUrls::articlePath($article->persistedId()));

            return FeedItem::create()
                ->id($link)
                ->title($article->getTitle())
                ->summary($article->excerpt())
                ->updated(CarbonImmutable::instance(
                    $article->getPublishedAt() ?? throw new LogicException('The published article has no date.'),
                ))
                ->link($link)
                ->authorName($article->getUser()->publicName());
        });
    }

    /**
     * Адрес страницы в карте сайта с датой изменения и языковыми версиями.
     */
    private function addUrl(Sitemap $sitemap, string $path, ?DateTimeInterface $modified = null): void
    {
        $url = Url::create($this->urls->to($path));

        if ($modified !== null) {
            $url->setLastModificationDate($modified);
        }

        $this->urls->alternates($path)->each(
            static fn (AlternateUrlData $alternate): Url => $url->addAlternate($alternate->href, $alternate->hreflang),
        );
        $sitemap->add($url);
    }
}
