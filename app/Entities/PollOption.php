<?php

declare(strict_types=1);

namespace App\Entities;

use App\Traits\HasPersistedId;
use Doctrine\ORM\Mapping as ORM;

/**
 * Вариант ответа в опросе.
 */
#[ORM\Entity]
#[ORM\Table(name: 'poll_options')]
class PollOption
{
    use HasPersistedId;

    /** Длина текста варианта — размер колонки text: вариант выводится строкой с полосой результата. */
    public const int TEXT_LENGTH = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poll::class, inversedBy: 'options')]
    #[ORM\JoinColumn(name: 'poll_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Poll $poll;

    #[ORM\Column(type: 'string', length: self::TEXT_LENGTH)]
    private string $text;

    #[ORM\Column(type: 'smallint')]
    private int $position;

    private function __construct()
    {
    }

    /**
     * Вариант на заданном месте опроса.
     *
     * @param Poll   $poll     опрос
     * @param string $text     текст варианта
     * @param int    $position место в порядке показа, с нуля
     */
    public static function create(Poll $poll, string $text, int $position): self
    {
        $option = new self();
        $option->poll = $poll;
        $option->text = $text;
        $option->position = $position;

        return $option;
    }

    /**
     * Идентификатор варианта.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Текст варианта.
     */
    public function getText(): string
    {
        return $this->text;
    }
}
