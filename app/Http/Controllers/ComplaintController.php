<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Admin\AdminPage;
use App\Http\Requests\Complaint\ResolveComplaintRequest;
use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Services\Complaint\ComplaintService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Жалобы читателей и решения модераторов.
 */
final class ComplaintController extends Controller
{
    /**
     * @param ResponseFactory  $responses  ответы
     * @param Redirector       $redirector перенаправления
     * @param ComplaintService $complaints жалобы
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ComplaintService $complaints,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Жалоба читателя.
     */
    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $this->complaints->file($request->user(), $request->toData());

        return $this->responses->json(['message' => __('messages.complaints.sent')], Response::HTTP_CREATED);
    }

    /**
     * Решение модератора по объекту в админке.
     */
    public function resolve(ResolveComplaintRequest $request): RedirectResponse
    {
        $this->complaints->resolve($request->target(), $request->targetId(), $request->decision(), $request->user());

        return $this->backToAdminPage(AdminPage::Complaints, __('admin.complaints.resolved'));
    }
}
