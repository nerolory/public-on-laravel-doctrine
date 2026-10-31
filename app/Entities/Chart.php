<?php

declare(strict_types=1);

namespace App\Entities;

use App\DTO\Media\ChartData;
use App\DTO\Media\ChartSeriesData;
use App\Enums\Media\ChartType;
use App\Traits\HasOwner;
use App\Traits\HasUuid;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use LaravelDoctrine\ORM\Contracts\UrlRoutable;

/**
 * График из таблицы, которую автор ввёл в редакторе: тип, заголовок, подписи и ряды. Рисует его фронтенд;
 * в тексте статьи график хранится блоком со ссылкой data-chart на UUID.
 * Данные неизменяемы: правка графика в редакторе создаёт новый.
 */
#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'charts')]
#[ORM\UniqueConstraint(name: 'charts_uuid_unique', columns: ['uuid'])]
class Chart implements UrlRoutable
{
    use HasOwner;
    use HasUuid;

    /** Длина заголовка графика — размер колонки title: заголовок выводится строкой над графиком. */
    public const int TITLE_LENGTH = 120;

    /** Длина подписи строки или названия ряда: подпись помещается на оси и в легенде. */
    public const int LABEL_LENGTH = 40;

    /** Длина типа графика — размер колонки type. */
    public const int TYPE_LENGTH = 8;

    /** Предел значения по модулю: больше числа не помещаются в подписи осей. */
    public const int VALUE_LIMIT = 1000000000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: self::UUID_LENGTH)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $owner;

    #[ORM\Column(type: 'string', length: self::TYPE_LENGTH, enumType: ChartType::class)]
    private ChartType $type;

    #[ORM\Column(type: 'string', length: self::TITLE_LENGTH)]
    private string $title;

    /** @var array{labels: list<string>, series: list<array{name: string, values: list<float>}>} */
    #[ORM\Column(type: 'json')]
    private array $data;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private DateTimeImmutable $createdAt;

    private function __construct()
    {
    }

    /**
     * Новый график.
     *
     * @param string    $uuid  UUID графика
     * @param ChartData $chart тип, заголовок и таблица
     * @param User|null $owner автор графика
     */
    public static function create(string $uuid, ChartData $chart, ?User $owner): self
    {
        $entity = new self();
        $entity->uuid = $uuid;
        $entity->type = $chart->type;
        $entity->title = $chart->title;
        $entity->data = $chart->table();
        $entity->owner = $owner;

        return $entity;
    }

    /**
     * Тип, заголовок и исходная таблица графика.
     */
    public function chart(): ChartData
    {
        return new ChartData(
            type: $this->type,
            title: $this->title,
            labels: collect($this->data['labels']),
            series: collect($this->data['series'])->map($this->seriesOf(...)),
        );
    }

    /**
     * Ряд графика из сохранённого массива; значения приводятся к float для диаграммы.
     *
     * @param array<string, mixed> $series название и значения ряда
     */
    private function seriesOf(array $series): ChartSeriesData
    {
        return new ChartSeriesData(
            name: $series['name'],
            values: collect($series['values'])->map(static fn (int|float $value): float => (float) $value),
        );
    }

    /**
     * Ключ записи в адресе: UUID.
     */
    public static function getRouteKeyNameStatic(): string
    {
        return 'uuid';
    }

    /**
     * UUID графика.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }

    /**
     * Заголовок графика.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Владелец графика — его автор; null — пользователь удалён.
     */
    private function owner(): ?User
    {
        return $this->owner;
    }
}
