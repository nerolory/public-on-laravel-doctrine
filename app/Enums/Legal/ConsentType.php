<?php

declare(strict_types=1);

namespace App\Enums\Legal;

use Illuminate\Support\Collection;

/**
 * Вид согласия. Каждый вид подтверждается конкретной версией текста документа.
 */
enum ConsentType: string
{
    /** Обработка персональных данных. */
    case PersonalData = 'personal_data';

    /** Правила сайта. */
    case SiteRules = 'site_rules';

    /** Подтверждение возраста. */
    case Age = 'age';

    /** Рассылка новостей и уведомлений. */
    case Mailing = 'mailing';

    /** Аналитические cookie. */
    case AnalyticsCookies = 'analytics_cookies';

    /** Маркетинговые cookie. */
    case MarketingCookies = 'marketing_cookies';

    /**
     * Можно ли отозвать согласие в настройках подписок. Согласия на ПДн, с правилами и о возрасте
     * отзываются только удалением учётной записи.
     */
    public function revocableInSettings(): bool
    {
        return in_array($this, [self::Mailing, self::AnalyticsCookies, self::MarketingCookies], strict: true);
    }

    /**
     * Виды согласий по значениям из формы; порядок сохраняется.
     *
     * @param Collection<int, string> $values
     *
     * @return Collection<int, self>
     */
    public static function fromValues(Collection $values): Collection
    {
        return $values->map(static fn (string $value): self => self::from($value))->values();
    }
}
