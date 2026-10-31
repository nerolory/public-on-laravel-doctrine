<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\User;

use App\Entities\PersonName;
use App\Entities\User;
use App\Services\User\UserService;
use App\Traits\BuildsAdminForms;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Text;

/**
 * Правка ФИО и email пользователя администратором.
 */
final class UserFormPage extends Page
{
    use BuildsAdminForms;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Адрес страницы в админке: /admin/page/user-form. */
    protected ?string $alias = 'user-form';

    /**
     * @param CoreContract $core    ядро MoonShine
     * @param UserService  $users   учётные записи
     * @param Gate         $gate    права доступа
     * @param UrlGenerator $urls    адреса отправки формы
     * @param Request      $request введённые значения после ошибки проверки
     */
    public function __construct(
        CoreContract $core,
        private readonly UserService $users,
        private readonly Gate $gate,
        private readonly UrlGenerator $urls,
        private readonly Request $request,
    ) {
        parent::__construct($core);
    }

    /**
     * Заголовок страницы.
     */
    public function getTitle(): string
    {
        return __('admin.users.edit');
    }

    /**
     * Хлебные крошки: админка → пользователи → правка.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminNestedBreadcrumbs(UserIndexPage::class, __('admin.users.title'), $this->getTitle());
    }

    /**
     * Форма с ФИО и email пользователя из параметра ?id=.
     *
     * @return iterable<Box>
     */
    protected function components(): iterable
    {
        $id = $this->queryId();

        if ($id === null) {
            throw new EntityNotFoundException('User id is missing in the query string.');
        }

        $user = $this->users->find($id);

        if ($user === null) {
            throw EntityNotFoundException::fromClassNameAndIdentifier(User::class, ['id' => (string) $id]);
        }

        $this->gate->authorize('manage', $user);

        return [Box::make([$this->form($id, $user)])];
    }

    /**
     * Форма ФИО и email: введённые значения после ошибки проверки, иначе сохранённые.
     */
    private function form(int $id, User $user): FormBuilder
    {
        $name = $user->getFullName();

        $form = FormBuilder::make($this->urls->route('admin.users.update', [$id]))->name('user-form')
            ->fields([
                $this->adminPutMethodField(),
                Text::make(__('admin.users.fields.last_name'), 'last_name')
                    ->customAttributes(['maxlength' => PersonName::PART_LENGTH]),
                Text::make(__('admin.users.fields.first_name'), 'first_name')
                    ->customAttributes(['maxlength' => PersonName::PART_LENGTH]),
                Text::make(__('admin.users.fields.middle_name'), 'middle_name')
                    ->customAttributes(['maxlength' => PersonName::PART_LENGTH]),
                Email::make(__('admin.users.fields.email'), 'email')
                    ->required()
                    ->customAttributes(['maxlength' => User::EMAIL_LENGTH]),
            ])
            ->fill([
                'last_name' => $this->request->old('last_name', $name->lastName()),
                'first_name' => $this->request->old('first_name', $name->firstName()),
                'middle_name' => $this->request->old('middle_name', $name->middleName()),
                'email' => $this->request->old('email', $user->getEmail()),
            ]);

        return $this->adminSubmit($form, __('admin.common.save'));
    }
}
