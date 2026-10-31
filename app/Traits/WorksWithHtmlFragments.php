<?php

declare(strict_types=1);

namespace App\Traits;

use Closure;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Collection;

/**
 * Разбор и правка HTML статьи через DOM: фрагмент, узлы, атрибуты и UUID в ссылках.
 */
trait WorksWithHtmlFragments
{
    /** UUID в ссылках data-image, data-chart и data-album. */
    private const string UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    /**
     * Корректные UUID из атрибута узлов без повторов.
     *
     * @return Collection<int, string>
     */
    private static function uuids(HTMLDocument $document, string $selector, string $attribute): Collection
    {
        $values = [];

        foreach ($document->body->querySelectorAll($selector) as $node) {
            $values[] = self::attribute($node, $attribute);
        }

        return collect($values)
            ->filter(static fn (string $value): bool => preg_match(self::UUID_PATTERN, $value) === 1)
            ->unique()
            ->values();
    }

    /**
     * Значение атрибута узла; отсутствующий атрибут — пустая строка, такой узел не находит записи.
     */
    private static function attribute(Element $node, string $name): string
    {
        return (string) $node->getAttribute($name);
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function setAttributes(Element $node, array $attributes): void
    {
        foreach ($attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }
    }

    /**
     * Убирает картинку, а вместе с ней и опустевшую обёртку figure.
     */
    private static function remove(Element $node): void
    {
        $figure = $node->closest('figure');
        $node->remove();

        // Сам узел figure уже отсоединён: повторно его не удаляем.
        if ($figure !== null && $figure->parentNode !== null && $figure->querySelector('img') === null) {
            $figure->remove();
        }
    }

    /**
     * Узлы, которые ссылаются на сущность по UUID: узел без найденной сущности убирается,
     * остальные передаются обработчику вместе с сущностью.
     *
     * @param Collection<string, object>     $entities сущности по UUID
     * @param Closure(Element, object): void $handle   правка узла найденной сущностью
     */
    private static function eachReferenced(
        HTMLDocument $document,
        string $selector,
        string $attribute,
        Collection $entities,
        Closure $handle,
    ): void {
        foreach ($document->body->querySelectorAll($selector) as $node) {
            $entity = $entities->get(self::attribute($node, $attribute));

            if ($entity === null) {
                self::remove($node);

                continue;
            }

            $handle($node, $entity);
        }
    }

    /**
     * Документ из фрагмента HTML; ошибки разбора не выводятся.
     */
    private static function parse(string $html): HTMLDocument
    {
        return HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NOERROR);
    }

    /**
     * Содержимое body без обёртки документа.
     */
    private static function serialize(HTMLDocument $document): string
    {
        return $document->body->innerHTML;
    }

    /**
     * Ячейка таблицы с текстом; у заголовка строки или столбца — scope.
     */
    private static function cell(HTMLDocument $document, string $tag, string $text, ?string $scope = null): Element
    {
        $cell = $document->createElement($tag);
        $cell->textContent = $text;

        if ($scope !== null) {
            $cell->setAttribute('scope', $scope);
        }

        return $cell;
    }

    /**
     * Подпись блока figure.
     */
    private static function caption(HTMLDocument $document, string $text): Element
    {
        $caption = $document->createElement('figcaption');
        $caption->textContent = $text;

        return $caption;
    }
}
