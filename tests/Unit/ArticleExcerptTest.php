<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Article;
use PHPUnit\Framework\TestCase;

/**
 * Анонс статьи: описание автора или начало текста без картинок и тегов.
 */
final class ArticleExcerptTest extends TestCase
{
    /**
     * Описание автора выводится без лишних пробелов по краям.
     */
    public function test_summary_is_used_as_is(): void
    {
        self::assertSame('Своё описание', Article::excerptOf('  Своё описание ', '<p>Текст</p>'));
    }

    /**
     * Длинный текст без описания обрезается до длины описания и заканчивается многоточием.
     */
    public function test_long_text_fills_summary_length_with_ellipsis(): void
    {
        $excerpt = Article::excerptOf(summary: null, content: '<p>' . str_repeat('слово ', 60) . '</p>');

        self::assertSame(Article::SUMMARY_LENGTH, mb_strlen($excerpt));
        self::assertStringEndsWith('…', $excerpt);
    }

    /**
     * В анонс не попадают подписи графиков, картинки и разрывы из-за тегов.
     */
    public function test_figures_and_tags_are_skipped(): void
    {
        $chart = '<figure data-type="chart"><img data-chart="x"><figcaption>Подпись</figcaption></figure>';
        self::assertSame('До после', Article::excerptOf(summary: null, content: '<p>До</p>' . $chart . '<p>после</p>'));
        self::assertSame(
            'Начало',
            Article::excerptOf(summary: null, content: '<p>Начало</p><figure><img data-image="x"><figcapt'),
        );
        self::assertSame(
            'Первый абзац второй',
            Article::excerptOf(summary: null, content: '<p>Первый&nbsp;абзац</p><p>второй</p>'),
        );
    }
}
