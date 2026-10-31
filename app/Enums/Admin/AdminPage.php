<?php

declare(strict_types=1);

namespace App\Enums\Admin;

/**
 * Страницы админки MoonShine. Значение — алиас страницы в параметре pageUri маршрута moonshine.page.
 */
enum AdminPage: string
{
    /** Список статей. */
    case Articles = 'articles';

    /** Дерево разделов статей. */
    case ArticleSections = 'article-sections';

    /** Альбомы на модерации. */
    case Albums = 'albums';

    /** Жалобы читателей. */
    case Complaints = 'complaints';

    /** Список пользователей. */
    case Users = 'users';

    /** Языки сайта. */
    case Languages = 'languages';

    /** Версии правовых документов. */
    case LegalDocuments = 'legal-documents';

    /** Настройки сайта по группам. */
    case SiteSettings = 'site-settings';
}
