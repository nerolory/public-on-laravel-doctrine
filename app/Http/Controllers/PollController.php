<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Entities\Poll;
use App\Http\Requests\Common\PageRequest;
use App\Http\Requests\Poll\RetractVoteRequest;
use App\Http\Requests\Poll\VotePollRequest;
use App\Services\Poll\PollService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;

/**
 * Голосование в опросах статей: только для зарегистрированных, результаты видны всем.
 */
final class PollController extends Controller
{
    /**
     * @param ResponseFactory $responses  ответы
     * @param Redirector      $redirector перенаправления
     * @param PollService     $polls      опросы
     */
    public function __construct(
        ResponseFactory $responses,
        Redirector $redirector,
        private readonly PollService $polls,
    ) {
        parent::__construct($responses, $redirector);
    }

    /**
     * Голос или переголосование.
     */
    public function vote(Poll $poll, VotePollRequest $request): JsonResponse
    {
        return $this->responses->json($this->polls->vote($poll, $request->user(), $request->optionIds()));
    }

    /**
     * Отзыв голоса.
     */
    public function retract(Poll $poll, RetractVoteRequest $request): JsonResponse
    {
        return $this->responses->json($this->polls->retract($poll, $request->user()));
    }

    /**
     * Страница проголосовавших за вариант.
     */
    public function voters(Poll $poll, int $option, PageRequest $request): JsonResponse
    {
        return $this->responses->json($this->polls->voters($poll, $option, $request->page()));
    }
}
