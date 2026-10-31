<?php

declare(strict_types=1);

namespace App\Entities;

use App\Enums\Reaction\ReactionType;
use Doctrine\ORM\Mapping as ORM;

/**
 * Реакция на статью.
 */
#[ORM\Entity]
class ArticleReaction extends Reaction
{
    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private Article $article;

    /**
     * @param Article      $article статья, на которую ставится реакция
     * @param User         $user    автор реакции
     * @param ReactionType $type    тип реакции
     */
    public function __construct(Article $article, User $user, ReactionType $type)
    {
        parent::__construct($user, $type);
        $this->article = $article;
    }
}
