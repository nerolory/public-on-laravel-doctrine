<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Admin\AdminPage;
use App\Http\Requests\Legal\StoreLegalDocumentRequest;
use App\Services\Legal\LegalDocumentService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;

/**
 * Публикация версий правовых документов из админки.
 */
final class LegalDocumentController extends Controller
{
    /**
     * @param ResponseFactory      $responses  ответы
     * @param Redirector           $redirector перенаправления
     * @param LegalDocumentService $documents  правовые документы
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly LegalDocumentService $documents,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Публикует новую версию документа.
     */
    public function store(StoreLegalDocumentRequest $request): RedirectResponse
    {
        $document = $this->documents->publish(
            $request->type(),
            $request->region(),
            $request->title(),
            $request->text(),
        );

        return $this->backToAdminPage(
            AdminPage::LegalDocuments,
            __('legal.admin.published', ['version' => $document->getVersion()]),
        );
    }
}
