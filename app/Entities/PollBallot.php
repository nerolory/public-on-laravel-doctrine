<?php

declare(strict_types=1);

namespace App\Entities;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Illuminate\Support\Collection;

/**
 * Бюллетень: выбор одного пользователя в одном опросе. Уникальность (poll, user) держит база.
 */
#[ORM\Entity]
#[ORM\Table(name: 'poll_ballots')]
#[ORM\UniqueConstraint(name: 'poll_ballots_poll_user_unique', columns: ['poll_id', 'user_id'])]
class PollBallot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Poll::class)]
    #[ORM\JoinColumn(name: 'poll_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Poll $poll;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var DoctrineCollection<int, PollOption> */
    #[ORM\ManyToMany(targetEntity: PollOption::class)]
    #[ORM\JoinTable(name: 'poll_ballot_options')]
    #[ORM\JoinColumn(name: 'ballot_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'option_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private DoctrineCollection $options;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $updatedAt;

    private function __construct()
    {
        $this->options = new ArrayCollection();
    }

    /**
     * Голос пользователя в опросе.
     *
     * @param Collection<int, PollOption> $options выбранные варианты
     */
    public static function cast(Poll $poll, User $user, Collection $options): self
    {
        $ballot = new self();
        $ballot->poll = $poll;
        $ballot->user = $user;
        $ballot->choose($options);

        return $ballot;
    }

    /**
     * Заменяет выбранные варианты.
     *
     * @param Collection<int, PollOption> $options
     */
    public function choose(Collection $options): void
    {
        $this->options->clear();
        $options->each(function (PollOption $option): void {
            $this->options->add($option);
        });
    }

    /**
     * Выбранные варианты.
     *
     * @return Collection<int, PollOption>
     */
    public function getOptions(): Collection
    {
        return collect($this->options->getValues());
    }
}
