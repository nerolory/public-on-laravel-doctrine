<?php

declare(strict_types=1);

namespace App\MoonShine\Pages\Settings;

use App\DTO\Admin\AdminLinkData;
use App\Enums\Settings\SettingGroup;
use App\Enums\Settings\SettingType;
use App\Enums\User\Ability;
use App\Services\Settings\SettingsSchema;
use App\Services\Settings\SettingsService;
use App\Support\Settings\SettingDefinition;
use App\Traits\AuthorizesAdminPage;
use App\Traits\BuildsAdminForms;
use App\Traits\ReadsCurrentRequest;
use App\Traits\ResolvesAdminUrls;
use Doctrine\ORM\EntityNotFoundException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\FlexibleRender;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Email;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Url;

/**
 * Настройки сайта по группам-вкладкам; форма строится из {@see SettingsSchema}.
 */
final class SiteSettingsPage extends Page
{
    use AuthorizesAdminPage;
    use BuildsAdminForms;
    use ReadsCurrentRequest;
    use ResolvesAdminUrls;

    /** Группа, которая открывается без параметра group. */
    private const SettingGroup DEFAULT_GROUP = SettingGroup::General;

    /** Адрес страницы в админке: /admin/page/site-settings. */
    protected ?string $alias = 'site-settings';

    /**
     * @param CoreContract    $core     ядро MoonShine
     * @param SettingsService $settings значения настроек
     * @param SettingsSchema  $schema   описания настроек
     * @param AuthFactory     $auth     текущий администратор
     * @param Gate            $gate     права доступа
     * @param ViewFactory     $views    шаблоны
     * @param UrlGenerator    $urls     адреса отправки формы
     * @param Request         $request  введённые значения после ошибки проверки
     */
    public function __construct(
        CoreContract $core,
        private readonly SettingsService $settings,
        private readonly SettingsSchema $schema,
        private readonly AuthFactory $auth,
        private readonly Gate $gate,
        private readonly ViewFactory $views,
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
        return __('settings.title');
    }

    /**
     * Хлебные крошки: админка → настройки.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return $this->adminBreadcrumbs($this->getTitle());
    }

    /**
     * Право на открытие страницы: управление настройками.
     */
    protected function ability(): Ability
    {
        return Ability::ManageSettings;
    }

    /**
     * Вкладки групп, форма выбранной группы и дополнительные действия почты и карты сайта.
     *
     * @return iterable<mixed>
     */
    protected function components(): iterable
    {
        $group = SettingGroup::tryFrom((string) $this->query('group', self::DEFAULT_GROUP->value))
            ?? throw new EntityNotFoundException('Unknown settings group in the query string.');
        $action = $this->urls->route('admin.settings.update', [$group->value]);

        return [
            FlexibleRender::make($this->views->make('admin.settings-tabs', ['tabs' => $this->tabs($group)])),
            Box::make([
                $this->adminSubmit(
                    FormBuilder::make($action)->name('settings-form')
                        ->fields([
                            $this->adminPutMethodField(),
                            ...$this->schema->group($group)->map($this->field(...)),
                        ])
                        ->fill($this->values($group)),
                    __('settings.save'),
                ),
            ]),
            ...$this->extraBlocks($group),
        ];
    }

    /**
     * Вкладки групп: подпись, адрес и признак открытой группы.
     *
     * @return Collection<int, AdminLinkData>
     */
    private function tabs(SettingGroup $current): Collection
    {
        return collect(SettingGroup::cases())->map(
            fn (SettingGroup $group): AdminLinkData => new AdminLinkData(
                label: __('settings.groups.' . $group->value),
                url: $this->getRoute(['group' => $group->value]),
                active: $group === $current,
            ),
        );
    }

    /**
     * Дополнительный блок группы: проверка почты или карта сайта.
     *
     * @return list<Box>
     */
    private function extraBlocks(SettingGroup $group): array
    {
        $view = $group->extraView();

        if ($view === null) {
            return [];
        }

        return [Box::make([FlexibleRender::make($this->views->make($view, [
            'email' => $this->auth->guard()->user()->getEmail(),
        ]))])];
    }

    /**
     * Значения полей формы: введённые до ошибки проверки или сохранённые; секреты не выводятся.
     *
     * @return array<string, mixed>
     */
    private function values(SettingGroup $group): array
    {
        return $this->schema->group($group)->mapWithKeys(fn (SettingDefinition $definition): array => [
            $definition->field() => $definition->type === SettingType::Secret
                ? ''
                : $this->request->old($definition->field(), $this->settings->get($definition->key)),
        ])->all();
    }

    /**
     * Поле формы по типу настройки с подсказкой из переводов.
     */
    private function field(SettingDefinition $definition): FieldContract
    {
        $label = __('settings.fields.' . $definition->key->value);
        $name = $definition->field();
        $field = match ($definition->type) {
            SettingType::Boolean => Switcher::make($label, $name),
            SettingType::Integer => Number::make($label, $name),
            SettingType::Text => Textarea::make($label, $name),
            SettingType::Secret => Password::make($label, $name)->customAttributes(['autocomplete' => 'new-password']),
            SettingType::Email => Email::make($label, $name),
            SettingType::Url => Url::make($label, $name),
            SettingType::Select => Select::make($label, $name)->options($this->options($definition)),
            SettingType::String => Text::make($label, $name),
        };
        $hint = $this->fieldHint($definition);

        if ($hint !== null) {
            $field->hint($hint);
        }

        return $field;
    }

    /**
     * Подсказка поля: для секрета — о скрытом значении, для настройки с подсказкой — её текст.
     */
    private function fieldHint(SettingDefinition $definition): ?string
    {
        $parts = [];

        if ($definition->type === SettingType::Secret) {
            $parts[] = __('settings.secret_hint');
        }

        if ($definition->hasHint) {
            $parts[] = __('settings.hints.' . $definition->key->value);
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * Варианты списка с подписями из lang/{locale}/settings.php.
     *
     * @return array<string, string>
     */
    private function options(SettingDefinition $definition): array
    {
        return collect($definition->options)
            ->mapWithKeys(fn (string $option): array => [
                $option => __('settings.options.' . $definition->key->value . '.' . $option),
            ])
            ->all();
    }
}
