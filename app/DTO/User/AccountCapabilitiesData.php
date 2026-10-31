<?php

declare(strict_types=1);

namespace App\DTO\User;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Что доступно владельцу аккаунта в личном кабинете.
 *
 * @implements Arrayable<string, bool>
 */
final readonly class AccountCapabilitiesData implements Arrayable
{
    /**
     * @param bool $deleteAccount может удалить свою учётную запись
     * @param bool $admin         открыта админка
     * @param bool $exportData    может выгрузить свои данные
     */
    public function __construct(
        public bool $deleteAccount,
        public bool $admin,
        public bool $exportData,
    ) {
    }

    /**
     * Возможности для ответа API.
     *
     * @return array{delete_account: bool, admin: bool, export_data: bool}
     */
    public function toArray(): array
    {
        return [
            'delete_account' => $this->deleteAccount,
            'admin' => $this->admin,
            'export_data' => $this->exportData,
        ];
    }
}
