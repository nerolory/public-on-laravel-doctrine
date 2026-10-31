<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\DTO\Settings\MailTestData;
use App\DTO\Settings\SettingsGroupData;
use App\DTO\Settings\SettingValueData;
use App\Entities\User;
use App\Enums\Settings\Counter;
use App\Enums\Settings\SettingKey;
use App\Enums\Settings\SettingType;
use App\Mail\TestMail;
use App\Repositories\SettingRepository;
use App\Support\Settings\SettingDefinition;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Настройки сайта из базы. Все значения кешируются целиком до изменения:
 * кеш сбрасывает подписчик Doctrine {@see \App\Doctrine\CacheInvalidationSubscriber} после сохранения.
 */
final class SettingsService
{
    /** Ключ кеша со всеми сохранёнными значениями. */
    public const string CACHE_KEY = 'settings.values';

    /**
     * @param SettingRepository $settings сохранённые значения
     * @param SettingsSchema    $schema   описания настроек
     * @param Cache             $cache    кеш значений
     * @param MailFactory       $mail     отправка проверочного письма
     */
    public function __construct(
        private readonly SettingRepository $settings,
        private readonly SettingsSchema $schema,
        private readonly Cache $cache,
        private readonly MailFactory $mail,
    ) {
    }

    /**
     * Значение настройки из базы.
     *
     * @throws LogicException настройки нет в базе: значения по умолчанию записывает SettingsSeeder
     */
    public function get(SettingKey $key): mixed
    {
        $values = $this->values();

        if (! $values->has($key->value)) {
            throw new LogicException('The setting is not stored: ' . $key->value);
        }

        return $values->get($key->value);
    }

    /**
     * Флаг: включение функции или счётчика.
     *
     * @throws LogicException в базе не логическое значение
     */
    public function boolean(SettingKey $key): bool
    {
        $value = $this->get($key);

        return is_bool($value) ? $value : throw self::wrongType($key, 'boolean');
    }

    /**
     * Обязательное целое значение: лимиты, размеры страниц, порт.
     *
     * @throws LogicException в базе не целое число
     */
    public function integer(SettingKey $key): int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : throw self::wrongType($key, 'integer');
    }

    /**
     * Заполненная строка: обязательная настройка (название сайта, отправитель, вариант из списка)
     * или ключ, заполненность которого вызывающий код уже проверил.
     *
     * @throws LogicException в базе не строка
     */
    public function string(SettingKey $key): string
    {
        $value = $this->get($key);

        return is_string($value) ? $value : throw self::wrongType($key, 'string');
    }

    /**
     * Необязательная строка: null, пока поле не заполнено в админке.
     *
     * @throws LogicException в базе не строка и не null
     */
    public function optionalString(SettingKey $key): ?string
    {
        $value = $this->get($key);

        return $value === null || is_string($value) ? $value : throw self::wrongType($key, 'string or null');
    }

    /**
     * Название сайта.
     */
    public function siteName(): string
    {
        return $this->string(SettingKey::GeneralSiteName);
    }

    /**
     * Включён ли счётчик аналитики или рекламы.
     */
    public function counterEnabled(Counter $counter): bool
    {
        return $this->boolean(SettingKey::counterEnabled($counter));
    }

    /**
     * Сохраняет группу из данных формы. Пустое поле секрета означает «оставить прежнее значение».
     *
     * @throws InvalidArgumentException поле не относится к группе
     */
    public function update(SettingsGroupData $data): void
    {
        $definitions = $this->schema->group($data->group)
            ->keyBy(static fn (SettingDefinition $definition): string => $definition->key->value);
        $values = $data->values
            ->reject(fn (SettingValueData $value): bool => $this->isBlankSecret($definitions, $value))
            ->map(fn (SettingValueData $value): SettingValueData => new SettingValueData(
                $value->key,
                self::definition($definitions, $value->key)->type->cast($value->value),
            ));

        $this->settings->save($values->values());
    }

    /**
     * Отправляет проверочное письмо администратору с сохранёнными настройками почты.
     */
    public function sendTestMail(User $admin): MailTestData
    {
        $email = $admin->getEmail();

        try {
            $this->mail->mailer()->to($email)->send(new TestMail($this->siteName()));
        } catch (TransportExceptionInterface $exception) {
            return new MailTestData(
                failed: true,
                message: __('mail.test.failed', ['error' => $exception->getMessage()]),
            );
        }

        return new MailTestData(
            failed: false,
            message: $this->boolean(SettingKey::MailEnabled)
                ? __('mail.test.sent', ['email' => $email])
                : __('mail.test.logged'),
        );
    }

    /**
     * Значения всех настроек по ключу; кеш хранит массив, чтобы не собирать его при каждом чтении.
     *
     * @return Collection<string, mixed>
     */
    private function values(): Collection
    {
        return collect($this->cache->rememberForever(self::CACHE_KEY, fn (): array => $this->settings->all()
            ->mapWithKeys(static fn (SettingValueData $value): array => [$value->key => $value->value])
            ->all()));
    }

    /**
     * Секрет, оставленный пустым в форме: прежнее значение сохраняется.
     *
     * @param Collection<string, SettingDefinition> $definitions
     */
    private function isBlankSecret(Collection $definitions, SettingValueData $value): bool
    {
        return self::definition($definitions, $value->key)->type === SettingType::Secret
            && in_array($value->value, [null, ''], strict: true);
    }

    /**
     * Ошибка типа сохранённого значения: тип задаёт SettingType при сохранении, другой тип означает порчу данных.
     */
    private static function wrongType(SettingKey $key, string $type): LogicException
    {
        return new LogicException('The setting ' . $key->value . ' is not of type ' . $type . '.');
    }

    /**
     * Описание поля группы; поле из чужой группы — ошибка запроса.
     *
     * @param Collection<string, SettingDefinition> $definitions
     *
     * @throws InvalidArgumentException
     */
    private static function definition(Collection $definitions, string $key): SettingDefinition
    {
        return $definitions->get($key)
            ?? throw new InvalidArgumentException('The setting is not in the group: ' . $key);
    }
}
