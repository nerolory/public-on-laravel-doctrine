<?php

declare(strict_types=1);

namespace App\DTO\Seo;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Включённый счётчик аналитики или рекламы для фронтенда.
 *
 * @implements Arrayable<string, string>
 */
final readonly class CounterData implements Arrayable
{
    /**
     * @param string $type    вид счётчика: yandex, ga4, gtm, vk, meta
     * @param string $id      идентификатор счётчика из настроек
     * @param string $consent категория согласия на cookies: analytics или marketing
     * @param string $script  адрес скрипта счётчика
     */
    public function __construct(
        public string $type,
        public string $id,
        public string $consent,
        public string $script,
    ) {
    }

    /**
     * Счётчик для ответа API.
     *
     * @return array{type: string, id: string, consent: string, script: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id, 'consent' => $this->consent, 'script' => $this->script];
    }
}
