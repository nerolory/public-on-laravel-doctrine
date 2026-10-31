<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Legal\UpdateConsentsRequest;
use App\Services\Legal\ConsentService;
use App\Services\Legal\DataExportService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Symfony\Component\HttpFoundation\Response;

/**
 * Правовые условия, согласия пользователя и выгрузка его данных.
 */
final class LegalController extends Controller
{
    /** Имя файла выгрузки данных. */
    private const string EXPORT_FILENAME = 'my-data.json';

    /**
     * @param ResponseFactory   $responses  ответы
     * @param Redirector        $redirector перенаправления
     * @param ConsentService    $consents   согласия
     * @param DataExportService $exports    выгрузка данных пользователя
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly ConsentService $consents,
        private readonly DataExportService $exports,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Условия регистрации и тексты документов для форм публичной части.
     */
    public function terms(): JsonResponse
    {
        return $this->responses->json($this->consents->terms());
    }

    /**
     * Состояние согласий и документы, которые нужно принять.
     */
    public function status(Request $request): JsonResponse
    {
        return $this->responses->json($this->consents->state($request->user())->toArray());
    }

    /**
     * Принимает и отзывает согласия.
     */
    public function update(UpdateConsentsRequest $request): JsonResponse
    {
        $state = $this->consents->update(
            $request->user(),
            $request->accepted(),
            $request->revoked(),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->responses->json(['message' => __('legal.saved'), ...$state->toArray()]);
    }

    /**
     * Выгрузка своих данных в машиночитаемом виде, если её предусматривают правила региона.
     */
    public function export(Request $request): JsonResponse
    {
        return $this->responses->json(
            $this->exports->export($request->user()),
            Response::HTTP_OK,
            ['Content-Disposition' => 'attachment; filename="' . self::EXPORT_FILENAME . '"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        );
    }
}
