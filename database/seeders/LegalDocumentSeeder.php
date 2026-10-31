<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\RegistrationPolicy;
use App\Enums\Legal\ConsentType;
use App\Services\Legal\LegalDocumentService;
use App\Services\Legal\Policies\EuropePolicy;
use App\Services\Legal\Policies\RussiaPolicy;
use App\Services\Legal\Policies\UnitedStatesPolicy;
use App\Services\Legal\RegistrationPolicies;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Заглушки правовых документов для регионов: их нужно заменить в админке на тексты, подготовленные юристом.
 * Повторный запуск не создаёт новых версий, если текст не менялся.
 */
final class LegalDocumentSeeder extends Seeder
{
    /** Заглушка в конце русского текста: документ нужно заменить. */
    private const string STUB_RU = 'Это заглушка документа для демонстрационного сайта. '
        . 'Замените текст в админке на подготовленный юристом.';

    /** Заглушка в конце английского текста: документ нужно заменить. */
    private const string STUB_EN = 'This is a placeholder for a demo site. '
        . 'Replace the text in the admin panel with one prepared by a lawyer.';

    /**
     * Публикует заглушки согласий, применимых к каждому региону.
     */
    public function run(LegalDocumentService $documents, RegistrationPolicies $policies): void
    {
        $minimumAges = $policies->all()->map(
            static fn (RegistrationPolicy $policy): int => $policy->minimumAge(),
        );

        foreach ($this->documents($minimumAges) as $region => $items) {
            foreach ($items as $type => [$title, $text]) {
                $documents->publish(ConsentType::from($type), $region, $title, $text);
            }
        }
    }

    /**
     * Тексты заглушек по регионам. Возраст подставляется из правил региона.
     *
     * @param Collection<string, int> $minimumAges возраст самостоятельного согласия по региону
     *
     * @return array<string, array<string, array{string, string}>>
     */
    private function documents(Collection $minimumAges): array
    {
        return [
            RussiaPolicy::REGION => $this->russia($minimumAges->get(RussiaPolicy::REGION)),
            EuropePolicy::REGION => $this->europe($minimumAges->get(EuropePolicy::REGION)),
            UnitedStatesPolicy::REGION => $this->unitedStates($minimumAges->get(UnitedStatesPolicy::REGION)),
        ];
    }

    /**
     * Заглушки для России: согласия по 152-ФЗ, правила, возраст, рассылка и cookies.
     *
     * @return array<string, array{string, string}>
     */
    private function russia(int $age): array
    {
        return [
            'personal_data' => [
                'Согласие на обработку персональных данных',
                'Я даю согласие на обработку моих персональных данных '
                    . '(адрес электронной почты, имя, сведения профиля) '
                    . 'в целях регистрации и работы на сайте в соответствии с Федеральным законом № 152-ФЗ '
                    . '«О персональных данных». '
                    . "Согласие действует до его отзыва путём удаления учётной записи.\n\n" . self::STUB_RU,
            ],
            'site_rules' => [
                'Правила сайта',
                'Публикуя материалы, вы соглашаетесь соблюдать законодательство, не размещать оскорбления, '
                    . 'разжигание ненависти, запрещённый контент и материалы, '
                    . "нарушающие авторские права.\n\n" . self::STUB_RU,
            ],
            'age' => ['Подтверждение возраста', "Подтверждаю, что мне исполнилось {$age} лет."],
            'mailing' => [
                'Согласие на получение рассылки',
                'Я согласен получать письма с подборками статей и новостями сайта. '
                    . "Согласие можно отозвать в настройках подписок.\n\n" . self::STUB_RU,
            ],
            'analytics_cookies' => [
                'Аналитические cookies',
                'Разрешаю использовать cookies сервисов статистики, '
                    . 'чтобы владельцы сайта понимали, какие материалы читают.',
            ],
            'marketing_cookies' => [
                'Маркетинговые cookies',
                'Разрешаю использовать cookies рекламных пикселей для оценки эффективности рекламы.',
            ],
        ];
    }

    /**
     * Заглушки для Европы: уведомление о конфиденциальности по GDPR, правила, возраст, рассылка и cookies.
     *
     * @return array<string, array{string, string}>
     */
    private function europe(int $age): array
    {
        return [
            'personal_data' => [
                'Privacy notice and consent',
                'I consent to the processing of my personal data (email address, name, profile details) '
                    . 'to create and run my account, as described in the privacy notice under the GDPR.'
                    . "\n\n" . self::STUB_EN,
            ],
            'site_rules' => [
                'Terms of use',
                'By publishing content you agree not to post insults, hate speech, illegal content '
                    . "or material that infringes copyright.\n\n" . self::STUB_EN,
            ],
            'age' => ['Age confirmation', "I confirm that I am at least {$age} years old."],
            'mailing' => [
                'Newsletter consent',
                'I want to receive emails with article digests and site news. '
                    . "I can withdraw this consent in my subscription settings.\n\n" . self::STUB_EN,
            ],
            'analytics_cookies' => [
                'Analytics cookies',
                'I allow analytics cookies so the site owners can see which content is read.',
            ],
            'marketing_cookies' => [
                'Marketing cookies',
                'I allow advertising pixel cookies to measure the effectiveness of advertising.',
            ],
        ];
    }

    /**
     * Заглушки для США: политика конфиденциальности, правила, возраст и рассылка; cookies не запрашиваются.
     *
     * @return array<string, array{string, string}>
     */
    private function unitedStates(int $age): array
    {
        return [
            'personal_data' => [
                'Privacy policy',
                'I have read the privacy policy and agree to the collection of my email address, name '
                    . "and profile details to run my account.\n\n" . self::STUB_EN,
            ],
            'site_rules' => [
                'Terms of use',
                'By publishing content you agree not to post insults, hate speech, illegal content '
                    . "or material that infringes copyright.\n\n" . self::STUB_EN,
            ],
            'age' => ['Age confirmation', "I confirm that I am at least {$age} years old."],
            'mailing' => [
                'Newsletter consent',
                'I want to receive emails with article digests and site news. '
                    . "I can opt out at any time.\n\n" . self::STUB_EN,
            ],
        ];
    }
}
