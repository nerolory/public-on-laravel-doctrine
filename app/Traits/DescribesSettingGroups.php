<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\Localization\LocalizationMode;
use App\Enums\Media\StorageDriver;
use App\Enums\Settings\MailEncryption;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\SettingType;
use App\Enums\Settings\ShareNetwork;
use App\Enums\Social\SocialProvider;
use App\Services\Legal\Policies\EuropePolicy;
use App\Services\Legal\Policies\RussiaPolicy;
use App\Services\Legal\Policies\UnitedStatesPolicy;
use App\Services\Settings\SettingsSchema;
use App\Support\Settings\SettingDefinition;

/**
 * Описания настроек по группам: тип, значение по умолчанию и правила проверки каждого поля.
 * Подключается в {@see SettingsSchema}, который собирает группы и значения.
 */
trait DescribesSettingGroups
{
    /** @return list<SettingDefinition> */
    private function general(): array
    {
        return [
            new SettingDefinition(
                SettingKey::GeneralSiteName,
                SettingType::String,
                default: SettingsSchema::SITE_NAME_DEFAULT,
                rules: ['max:120'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function content(): array
    {
        return [...$this->contentLimits(), ...$this->chartAndPollLimits(), ...$this->complaints()];
    }

    /** @return list<SettingDefinition> */
    private function contentLimits(): array
    {
        return [
            new SettingDefinition(
                SettingKey::ContentArticleImages,
                SettingType::Integer,
                default: 10,
                rules: ['min:1', 'max:50'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::ContentArticleTags,
                SettingType::Integer,
                default: 10,
                rules: ['min:1', 'max:50'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::ContentAlbumPhotos,
                SettingType::Integer,
                default: 10,
                rules: ['min:1', 'max:50'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::ContentCommentLength,
                SettingType::Integer,
                default: 2000,
                rules: ['min:100', 'max:10000'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function chartAndPollLimits(): array
    {
        return [
            new SettingDefinition(
                SettingKey::ContentChartLabels,
                SettingType::Integer,
                default: 20,
                rules: ['min:2', 'max:50'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::ContentChartSeries,
                SettingType::Integer,
                default: 5,
                rules: ['min:1', 'max:10'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::ContentPollMaxOptions,
                SettingType::Integer,
                default: 10,
                rules: ['min:2', 'max:20'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::ContentPollPreviewVoters,
                SettingType::Integer,
                default: 10,
                rules: ['min:0', 'max:50'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function complaints(): array
    {
        return [
            new SettingDefinition(SettingKey::ContentComplaintsAutoHide, SettingType::Boolean, default: false),
            new SettingDefinition(
                SettingKey::ContentComplaintsThreshold,
                SettingType::Integer,
                default: 5,
                rules: ['min:2', 'max:100'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function pagination(): array
    {
        return [...$this->listSizes(), ...$this->adminSizes()];
    }

    /** @return list<SettingDefinition> */
    private function listSizes(): array
    {
        return [
            new SettingDefinition(
                SettingKey::PaginationArticles,
                SettingType::Integer,
                default: 12,
                rules: ['min:1', 'max:100'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationComments,
                SettingType::Integer,
                default: 20,
                rules: ['min:1', 'max:100'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationAlbums,
                SettingType::Integer,
                default: 12,
                rules: ['min:1', 'max:100'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationPollVoters,
                SettingType::Integer,
                default: 30,
                rules: ['min:1', 'max:200'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationFeed,
                SettingType::Integer,
                default: 20,
                rules: ['min:1', 'max:100'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function adminSizes(): array
    {
        return [
            new SettingDefinition(
                SettingKey::PaginationAdmin,
                SettingType::Integer,
                default: 20,
                rules: ['min:5', 'max:200'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationRating,
                SettingType::Integer,
                default: 50,
                rules: ['min:5', 'max:500'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationStatisticsTop,
                SettingType::Integer,
                default: 10,
                rules: ['min:1', 'max:100'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationTopAuthors,
                SettingType::Integer,
                default: 5,
                rules: ['min:1', 'max:50'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::PaginationTagSuggestions,
                SettingType::Integer,
                default: 10,
                rules: ['min:1', 'max:50'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function statistics(): array
    {
        return [
            new SettingDefinition(
                SettingKey::StatisticsViewsRetentionMonths,
                SettingType::Integer,
                default: 6,
                rules: ['min:1', 'max:36'],
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function seo(): array
    {
        return [
            new SettingDefinition(SettingKey::SeoNoindex, SettingType::Boolean, default: true),
            new SettingDefinition(SettingKey::SeoHomeTitle, SettingType::String, default: null, rules: ['max:255']),
            new SettingDefinition(
                SettingKey::SeoHomeDescription,
                SettingType::Text,
                default: null,
                rules: ['max:320'],
            ),
            new SettingDefinition(SettingKey::SeoDefaultImage, SettingType::Url),
            new SettingDefinition(SettingKey::SeoOgEnabled, SettingType::Boolean, default: true),
            new SettingDefinition(SettingKey::SeoTwitterEnabled, SettingType::Boolean, default: true),
            new SettingDefinition(
                SettingKey::SeoTwitterSite,
                SettingType::String,
                default: null,
                rules: ['max:40', 'regex:/^@?[A-Za-z0-9_]+$/'],
            ),
            new SettingDefinition(
                SettingKey::SeoVerificationYandex,
                SettingType::String,
                default: null,
                rules: ['max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            ),
            new SettingDefinition(
                SettingKey::SeoVerificationGoogle,
                SettingType::String,
                default: null,
                rules: ['max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            ),
            new SettingDefinition(SettingKey::SeoRssEnabled, SettingType::Boolean, default: true),
            new SettingDefinition(SettingKey::SeoRobotsExtra, SettingType::Text, default: null, rules: ['max:2000']),
        ];
    }

    /** @return list<SettingDefinition> */
    private function share(): array
    {
        return [
            new SettingDefinition(SettingKey::share(ShareNetwork::Vk), SettingType::Boolean, default: true),
            new SettingDefinition(SettingKey::share(ShareNetwork::Ok), SettingType::Boolean, default: true),
            new SettingDefinition(SettingKey::share(ShareNetwork::Telegram), SettingType::Boolean, default: true),
            new SettingDefinition(SettingKey::share(ShareNetwork::Facebook), SettingType::Boolean, default: false),
            new SettingDefinition(SettingKey::share(ShareNetwork::X), SettingType::Boolean, default: false),
        ];
    }

    /** @return list<SettingDefinition> */
    private function metrics(): array
    {
        return [...$this->analyticsCounters(), ...$this->tagAndAdCounters()];
    }

    /** @return list<SettingDefinition> */
    private function analyticsCounters(): array
    {
        return [
            new SettingDefinition(SettingKey::MetricsYandexEnabled, SettingType::Boolean, default: false),
            new SettingDefinition(
                SettingKey::MetricsYandexId,
                SettingType::String,
                default: null,
                rules: ['regex:/^\d{4,12}$/'],
            ),
            new SettingDefinition(SettingKey::MetricsYandexToken, SettingType::Secret),
            new SettingDefinition(SettingKey::MetricsGa4Enabled, SettingType::Boolean, default: false),
            new SettingDefinition(
                SettingKey::MetricsGa4Id,
                SettingType::String,
                default: null,
                rules: ['regex:/^G-[A-Z0-9]{4,20}$/'],
            ),
            new SettingDefinition(
                SettingKey::MetricsGa4Property,
                SettingType::String,
                default: null,
                rules: ['regex:/^\d{4,20}$/'],
            ),
            new SettingDefinition(SettingKey::MetricsGa4Credentials, SettingType::Secret),
        ];
    }

    /** @return list<SettingDefinition> */
    private function tagAndAdCounters(): array
    {
        return [
            new SettingDefinition(SettingKey::MetricsGtmEnabled, SettingType::Boolean, default: false),
            new SettingDefinition(
                SettingKey::MetricsGtmId,
                SettingType::String,
                default: null,
                rules: ['regex:/^GTM-[A-Z0-9]{4,12}$/'],
            ),
            new SettingDefinition(SettingKey::MetricsVkEnabled, SettingType::Boolean, default: false),
            new SettingDefinition(
                SettingKey::MetricsVkId,
                SettingType::String,
                default: null,
                rules: ['regex:/^\d{4,12}$/'],
            ),
            new SettingDefinition(SettingKey::MetricsMetaEnabled, SettingType::Boolean, default: false),
            new SettingDefinition(
                SettingKey::MetricsMetaId,
                SettingType::String,
                default: null,
                rules: ['regex:/^\d{6,20}$/'],
            ),
        ];
    }

    /**
     * Регион правил регистрации. Варианты берутся из стратегий регионов.
     *
     * @return list<SettingDefinition>
     */
    private function legal(): array
    {
        return [
            new SettingDefinition(
                SettingKey::LegalRegion,
                SettingType::Select,
                default: RussiaPolicy::REGION,
                options: [RussiaPolicy::REGION, EuropePolicy::REGION, UnitedStatesPolicy::REGION],
                required: true,
                hasHint: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function localization(): array
    {
        return [
            new SettingDefinition(
                SettingKey::LocalizationMode,
                SettingType::Select,
                default: LocalizationMode::Single->value,
                options: LocalizationMode::values()->all(),
                required: true,
                hasHint: true,
            ),
            new SettingDefinition(
                SettingKey::LocalizationBaseDomain,
                SettingType::String,
                default: null,
                rules: [
                    'required_if:localization__mode,' . LocalizationMode::Subdomain->value,
                    'regex:/^[a-z0-9.-]+$/',
                ],
                hasHint: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function profile(): array
    {
        return [
            new SettingDefinition(
                SettingKey::ProfileNetworkFacebook,
                SettingType::Boolean,
                default: false,
                hasHint: true,
            ),
            new SettingDefinition(SettingKey::ProfileNetworkX, SettingType::Boolean, default: false),
        ];
    }

    /**
     * У каждого OAuth-провайдера включение, идентификатор и секрет приложения; у Telegram — токен бота.
     *
     * @return list<SettingDefinition>
     */
    private function social(): array
    {
        $oauth = SocialProvider::oauth()->flatMap(static fn (SocialProvider $provider): array => [
            new SettingDefinition(
                SettingKey::socialEnabled($provider),
                SettingType::Boolean,
                default: false,
                hasHint: $provider->hasHint(),
            ),
            new SettingDefinition(SettingKey::socialCredential($provider), SettingType::String),
            new SettingDefinition(SettingKey::socialClientSecret($provider), SettingType::Secret),
        ]);

        return [
            ...$oauth,
            new SettingDefinition(
                SettingKey::socialEnabled(SocialProvider::Telegram),
                SettingType::Boolean,
                default: false,
            ),
            new SettingDefinition(
                SettingKey::socialCredential(SocialProvider::Telegram),
                SettingType::Secret,
                hasHint: true,
            ),
        ];
    }

    /**
     * @param string $fromAddress адрес отправителя по умолчанию
     *
     * @return list<SettingDefinition>
     */
    private function mail(string $fromAddress): array
    {
        return [
            new SettingDefinition(SettingKey::MailEnabled, SettingType::Boolean, default: false, hasHint: true),
            new SettingDefinition(SettingKey::MailHost, SettingType::String),
            new SettingDefinition(
                SettingKey::MailPort,
                SettingType::Integer,
                default: 587,
                rules: ['min:1', 'max:65535'],
                required: true,
            ),
            new SettingDefinition(
                SettingKey::MailEncryption,
                SettingType::Select,
                default: MailEncryption::Tls->value,
                options: MailEncryption::values()->all(),
                required: true,
            ),
            new SettingDefinition(SettingKey::MailUsername, SettingType::String),
            new SettingDefinition(SettingKey::MailPassword, SettingType::Secret),
            new SettingDefinition(
                SettingKey::MailFromAddress,
                SettingType::Email,
                default: $fromAddress,
                required: true,
            ),
            new SettingDefinition(
                SettingKey::MailFromName,
                SettingType::String,
                default: SettingsSchema::SITE_NAME_DEFAULT,
                required: true,
            ),
        ];
    }

    /** @return list<SettingDefinition> */
    private function storage(): array
    {
        return [
            new SettingDefinition(
                SettingKey::StorageDriver,
                SettingType::Select,
                default: StorageDriver::Local->value,
                options: StorageDriver::values()->all(),
                required: true,
                hasHint: true,
            ),
            new SettingDefinition(SettingKey::StorageS3Key, SettingType::String),
            new SettingDefinition(SettingKey::StorageS3Secret, SettingType::Secret),
            new SettingDefinition(
                SettingKey::StorageS3Region,
                SettingType::String,
                default: 'ru-central1',
                rules: ['required_if:storage__driver,' . StorageDriver::S3->value],
            ),
            new SettingDefinition(SettingKey::StorageS3Bucket, SettingType::String),
            new SettingDefinition(SettingKey::StorageS3Endpoint, SettingType::Url, hasHint: true),
            new SettingDefinition(SettingKey::StorageS3Url, SettingType::Url, hasHint: true),
            new SettingDefinition(SettingKey::StorageS3PathStyle, SettingType::Boolean, default: true),
        ];
    }
}
