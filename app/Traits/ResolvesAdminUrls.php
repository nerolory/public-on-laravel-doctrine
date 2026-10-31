<?php

declare(strict_types=1);

namespace App\Traits;

use LogicException;

/**
 * Адреса корня админки и страниц MoonShine по их классам.
 */
trait ResolvesAdminUrls
{
    /**
     * Адрес корня админки.
     */
    protected function adminHomeUrl(): string
    {
        return $this->getCore()->getRouter()->getEndpoints()->home();
    }

    /**
     * Адрес страницы админки по её классу.
     *
     * @param class-string                   $page   класс страницы, зарегистрированной в MoonShine
     * @param array<string, string|int|null> $params параметры адреса: id, group, type, region и т.п.
     *
     * @throws LogicException страница не зарегистрирована в MoonShine
     */
    protected function adminPageUrl(string $page, array $params = []): string
    {
        $target = $this->getCore()->getPages()->findByClass($page)
            ?? throw new LogicException('Admin page is not registered: ' . $page);

        return $target->getRoute($params);
    }

    /**
     * Хлебные крошки из двух звеньев: админка и текущий раздел.
     *
     * @return array<string, string>
     */
    protected function adminBreadcrumbs(string $current): array
    {
        return [$this->adminHomeUrl() => __('admin.breadcrumbs.admin'), '#' => $current];
    }

    /**
     * Хлебные крошки из трёх звеньев: админка, раздел со страницей списка и текущая страница.
     *
     * @param class-string $sectionPage страница раздела, зарегистрированная в MoonShine
     *
     * @return array<string, string>
     */
    protected function adminNestedBreadcrumbs(string $sectionPage, string $sectionTitle, string $current): array
    {
        return [
            $this->adminHomeUrl() => __('admin.breadcrumbs.admin'),
            $this->adminPageUrl($sectionPage) => $sectionTitle,
            '#' => $current,
        ];
    }
}
