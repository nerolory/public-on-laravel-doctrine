<?php

declare(strict_types=1);

namespace App\Doctrine;

use App\Entities\Album;
use App\Entities\Language;
use App\Entities\LegalDocument;
use App\Entities\Setting;
use App\Services\Article\ArticleContent;
use App\Services\Legal\ConsentService;
use App\Services\Localization\LocalizationService;
use App\Services\Settings\SettingsService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Сбрасывает кеш, построенный по сущностям, которые меняются редко и читаются на каждом запросе.
 * В onFlush изменения только собираются, кеш очищается после успешной записи в postFlush.
 */
final class CacheInvalidationSubscriber implements EventSubscriber
{
    /** Ключи кеша, которые зависят от сущности. */
    private const array DEPENDENCIES = [
        Setting::class => [SettingsService::CACHE_KEY, LocalizationService::CACHE_KEY, ConsentService::CACHE_KEY],
        Language::class => [LocalizationService::CACHE_KEY],
        LegalDocument::class => [ConsentService::CACHE_KEY],
        Album::class => [ArticleContent::EMBEDS_KEY],
    ];

    /** @var array<string, true> ключи кеша к сбросу после записи */
    private array $staleKeys = [];

    /**
     * @param Cache $cache кеш приложения
     */
    public function __construct(
        private readonly Cache $cache,
    ) {
    }

    /**
     * События Doctrine: сбор изменений и запись.
     *
     * @return list<string>
     */
    public function getSubscribedEvents(): array
    {
        return [Events::onFlush, Events::postFlush];
    }

    /**
     * Запоминает ключи кеша по добавленным, изменённым и удалённым сущностям.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        $entities = [
            ...$unitOfWork->getScheduledEntityInsertions(),
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
        ];

        foreach ($entities as $entity) {
            $this->collect($entity);
        }
    }

    /**
     * Сбрасывает собранные ключи кеша после успешной записи.
     */
    public function postFlush(PostFlushEventArgs $args): void
    {
        foreach (array_keys($this->staleKeys) as $key) {
            $this->cache->forget($key);
        }

        $this->staleKeys = [];
    }

    /**
     * Проверка через instanceof: изменённая сущность может оказаться прокси-объектом Doctrine.
     */
    private function collect(object $entity): void
    {
        foreach (self::DEPENDENCIES as $class => $keys) {
            if ($entity instanceof $class) {
                $this->staleKeys += array_fill_keys($keys, true);
            }
        }
    }
}
