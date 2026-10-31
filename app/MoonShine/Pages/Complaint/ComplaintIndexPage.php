<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Complaint;

use App\DTO\Admin\AdminComplaintRowData;
use App\DTO\Complaint\ComplaintGroupData;
use App\Enums\Complaint\ComplaintAction;
use App\Services\Complaint\ComplaintService;
use App\Services\Complaint\ComplaintTargets;
use App\Support\Settings\PageSizes;
use App\Traits\PaginatesList;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Illuminate\Contracts\View\Factory as ViewFactory;
use LogicException;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;

/**
 * Очередь жалоб: объекты с открытыми жалобами, решение — скрыть или отклонить.
 */
final class ComplaintIndexPage extends Page
{
    use PaginatesList;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/complaints. */
    protected ?string $alias = 'complaints';

    /**
     * @param CoreContract     $core       ядро MoonShine
     * @param ComplaintService $complaints жалобы
     * @param ComplaintTargets $targets    типы объектов жалоб с подписями кнопок
     * @param PageSizes        $pageSizes  размер страницы очереди
     * @param ViewFactory      $views      шаблоны
     */
    public function __construct(
        CoreContract $core,
        private readonly ComplaintService $complaints,
        private readonly ComplaintTargets $targets,
        private readonly PageSizes $pageSizes,
        private readonly ViewFactory $views,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.complaints.title');
    }

    /**
     * Хлебные крошки: админка → жалобы.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Таблица очереди жалоб.
     *
     * @return iterable<object>
     */
    protected function components(): iterable
    {
        $page = $this->pageNumber();
        $size = $this->pageSizes->admin();
        $groups = $this->complaints->queue($page, $size);

        return $this->listComponents(
            $this->views->make('admin.complaints', [
                'groups' => $groups->items->map($this->row(...)),
                'hideAction' => ComplaintAction::Hide->value,
                'dismissAction' => ComplaintAction::Dismiss->value,
            ]),
            $groups,
            $page,
            $size,
        );
    }

    /**
     * Строка очереди: объект, число жалоб, причины и тексты.
     */
    private function row(ComplaintGroupData $group): AdminComplaintRowData
    {
        $label = $group->label ?? throw new LogicException('Complaint group has no label: ' . $group->target);

        return new AdminComplaintRowData(
            id: $group->id,
            target: $group->target,
            total: $group->total,
            labelText: $label->text,
            labelUrl: $label->url,
            texts: $group->texts,
            reasonLines: $group->reasons->map($this->reasonLine(...))->values(),
            hideLabel: $this->targets->get($group->target)->hideLabel(),
        );
    }

    /**
     * Строка «причина: число» для списка причин жалоб.
     */
    private function reasonLine(int $count, string $reason): string
    {
        return __('admin.complaints.reason_names.' . $reason) . ': ' . $count;
    }
}
