<?php

declare(strict_types=1);

namespace App\Http\Requests\User;

use App\Entities\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Действие администратора над пользователем из адреса /users/{user}: только над теми, кем он вправе управлять.
 */
class ManageUserRequest extends FormRequest
{
    /**
     * Право управлять пользователем по UserPolicy.
     */
    public function authorize(Gate $gate): Response
    {
        return $gate->inspect('manage', $this->target());
    }

    /**
     * Пользователь из адреса.
     */
    public function target(): User
    {
        return $this->route('user');
    }
}
