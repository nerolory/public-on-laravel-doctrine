<?php

declare(strict_types=1);

namespace App\Listeners\Mail;

use App\Enums\Settings\MailEncryption;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Переносит настройки почты из админки в конфигурацию почты Laravel.
 * Обработчик очереди живёт долго, поэтому настройки применяются заново перед каждой задачей.
 * Если отправка через SMTP выключена, письма уходят транспортом из окружения (по умолчанию запись в журнал).
 */
final class ApplyMailSettings
{
    /** Префикс имени почтового транспорта, собранного из настроек сайта. */
    private const string MAILER_PREFIX = 'site_';

    /** Ожидание ответа SMTP-сервера в секундах: дольше задача очереди не ждёт. */
    private const int SMTP_TIMEOUT = 10;

    /** Ключ конфигурации: имя транспорта по умолчанию из окружения, запомненное при первом применении настроек. */
    private const string ENVIRONMENT_KEY = 'mail.environment';

    /**
     * @param SettingsService $settings настройки сайта
     * @param Config          $config   конфигурация приложения
     */
    public function __construct(
        private readonly SettingsService $settings,
        private readonly Config $config,
    ) {
    }

    /**
     * Перед задачей очереди: письма задачи уходят с актуальными настройками.
     */
    public function handle(JobProcessing $event): void
    {
        $this->apply();
    }

    /**
     * Делает транспортом по умолчанию транспорт из настроек сайта. Имя транспорта зависит от его параметров
     * и отправителя, поэтому после смены настроек менеджер почты создаёт новый транспорт.
     */
    public function apply(): void
    {
        $environmentMailer = $this->environmentMailer();
        $from = $this->from();
        $transport = $this->settings->boolean(SettingKey::MailEnabled)
            ? $this->smtp()
            : $this->config->get('mail.mailers.' . $environmentMailer);
        $name = self::MAILER_PREFIX . hash('xxh3', serialize([$transport, $from]));

        $this->config->set('mail.from', $from);
        $this->config->set('mail.mailers.' . $name, $transport);
        $this->config->set('mail.default', $name);
    }

    /**
     * Отправитель из настроек сайта: адрес и имя обязательны, при установке имя — название сайта.
     *
     * @return array{address: string, name: string}
     */
    private function from(): array
    {
        return [
            'address' => $this->settings->string(SettingKey::MailFromAddress),
            'name' => $this->settings->string(SettingKey::MailFromName),
        ];
    }

    /**
     * Имя транспорта по умолчанию из окружения: запоминается при первом вызове, до замены настройками сайта.
     */
    private function environmentMailer(): string
    {
        if (! $this->config->has(self::ENVIRONMENT_KEY)) {
            $this->config->set(self::ENVIRONMENT_KEY, (string) $this->config->get('mail.default'));
        }

        return (string) $this->config->get(self::ENVIRONMENT_KEY);
    }

    /**
     * Параметры SMTP-транспорта; схему и TLS задаёт выбранное шифрование.
     *
     * @return array<string, mixed>
     */
    private function smtp(): array
    {
        $encryption = MailEncryption::from($this->settings->string(SettingKey::MailEncryption));

        return [
            'transport' => 'smtp',
            'scheme' => $encryption->scheme(),
            'host' => $this->settings->optionalString(SettingKey::MailHost),
            'port' => $this->settings->integer(SettingKey::MailPort),
            'username' => $this->settings->optionalString(SettingKey::MailUsername),
            'password' => $this->settings->optionalString(SettingKey::MailPassword),
            'timeout' => self::SMTP_TIMEOUT,
            'auto_tls' => $encryption->autoTls(),
            'require_tls' => $encryption->requiresTls(),
        ];
    }
}
