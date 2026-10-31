<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\SocialAccount;
use App\Entities\User;
use App\Enums\Settings\SettingGroup;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\DoctrineTestCase;

/**
 * Вход через соцсети: список провайдеров, OAuth-перенаправления, связывание почты, согласия и Telegram.
 */
final class SocialAuthTest extends DoctrineTestCase
{
    /** Токен тестового бота Telegram: им подписываются данные входа в тестах. */
    private const string BOT_TOKEN = '123456:test-token';

    /**
     * Список входа содержит только включённые провайдеры с заполненными ключами.
     */
    public function test_only_enabled_and_configured_providers_are_listed(): void
    {
        $this->getJson('/api/auth/providers')->assertExactJson(['items' => []]);
        $this->enable('vkid');
        $this->enableTelegram();

        $items = $this->getJson('/api/auth/providers')->json('items');
        self::assertSame(['vkid', 'telegram'], array_column($items, 'network'));
        self::assertStringEndsWith('/api/auth/vkid/redirect', $items[0]['url']);
        self::assertStringStartsWith('https://oauth.telegram.org/auth?bot_id=123456', $items[1]['url']);
        $this->get('/api/auth/google/redirect')->assertNotFound();
    }

    /**
     * Apple и Facebook входят только после включения: перенаправление ведёт на их страницы с ключом приложения.
     */
    public function test_apple_and_facebook_redirect_to_provider_only_when_enabled(): void
    {
        $this->get('/api/auth/apple/redirect')->assertNotFound();
        $this->get('/api/auth/facebook/redirect')->assertNotFound();
        $this->enable('apple');
        $this->enable('facebook');

        self::assertSame(
            ['apple', 'facebook'],
            array_column($this->getJson('/api/auth/providers')->json('items'), 'network'),
        );
        $apple = $this->get('/api/auth/apple/redirect')->assertRedirect()->headers->get('Location');
        self::assertStringStartsWith('https://appleid.apple.com/auth/authorize', $apple);
        self::assertStringContainsString('client_id=app-apple', $apple);
        self::assertStringContainsString('response_mode=form_post', $apple);
        $facebook = $this->get('/api/auth/facebook/redirect')->assertRedirect()->headers->get('Location');
        self::assertMatchesRegularExpression('#^https://www\.facebook\.com/v\d+\.\d+/dialog/oauth#', $facebook);
        self::assertStringContainsString('client_id=app-facebook', $facebook);
    }

    /**
     * Вход через Apple: почта существующего пользователя связывает аккаунт Apple и открывает сессию.
     */
    public function test_apple_callback_signs_in_existing_user(): void
    {
        $this->enable('apple');
        $user = $this->createUser('apple@example.test', 'Аня');
        $this->fakeSocialite('apple', 'apple-1', 'apple@example.test', 'Аня');

        $this->get('/api/auth/apple/callback')->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->em->clear();
        self::assertSame(1, $this->em->getRepository(SocialAccount::class)->count(['provider' => 'apple']));
    }

    /**
     * Вход через Facebook: почта существующего пользователя связывает аккаунт Facebook и открывает сессию.
     */
    public function test_facebook_callback_signs_in_existing_user(): void
    {
        $this->enable('facebook');
        $user = $this->createUser('facebook@example.test', 'Борис');
        $this->fakeSocialite('facebook', 'fb-1', 'facebook@example.test', 'Борис');

        $this->get('/api/auth/facebook/callback')->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->em->clear();
        self::assertSame(1, $this->em->getRepository(SocialAccount::class)->count(['provider' => 'facebook']));
    }

    /**
     * Перенаправление OAuth использует ключи приложения из настроек сайта и PKCE.
     */
    public function test_redirect_uses_keys_from_site_settings(): void
    {
        $this->enable('vkid');
        $location = $this->get('/api/auth/vkid/redirect')->assertRedirect()->headers->get('Location');

        self::assertStringStartsWith('https://id.vk.ru/authorize', $location);
        self::assertStringContainsString('client_id=app-vkid', $location);
        self::assertStringContainsString('code_challenge=', $location, 'VK ID 2.1 использует PKCE.');
        self::assertStringContainsString(urlencode('/api/auth/vkid/callback'), $location);
    }

    /**
     * Вход через соцсеть с почтой существующего пользователя связывает аккаунт и подтверждает почту.
     */
    public function test_existing_email_is_linked_and_signed_in(): void
    {
        $this->enable('yandex');
        $user = $this->createUser('ivan@example.test', 'Иван');
        $this->fakeSocialite('yandex', 'ya-1', 'ivan@example.test', 'Иван Петров');

        $this->get('/api/auth/yandex/callback')->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
        $this->em->clear();
        self::assertNotNull($this->em->find(User::class, $user->getAuthIdentifier())->getEmailVerifiedAt());
        self::assertSame(1, $this->em->getRepository(SocialAccount::class)->count(['provider' => 'yandex']));
    }

    /**
     * Новый пользователь через соцсеть сначала принимает согласия, и только потом аккаунт создаётся.
     */
    public function test_new_user_accepts_consents_before_account_is_created(): void
    {
        $this->enable('google');
        $this->fakeSocialite('google', 'g-1', 'new@example.test', 'Анна');

        $this->get('/api/auth/google/callback')->assertRedirect('/register/social');
        $this->assertGuest();
        $this->getJson('/api/auth/social/pending')
            ->assertJson(['provider' => 'google', 'name' => 'Анна', 'email' => 'new@example.test']);
        $this->postJson('/api/auth/social/complete', ['email' => 'new@example.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consents.personal_data');
        $this->postJson('/api/auth/social/complete', [
            'email' => 'new@example.test',
            'consents' => ['personal_data' => true, 'site_rules' => true, 'age' => true],
        ])->assertCreated();

        $this->assertAuthenticated();
        $this->getJson('/api/session')
            ->assertJsonPath('user.name', 'Анна')
            ->assertJsonPath('user.email_verified', true);
        $this->postJson('/api/logout')->assertOk();

        $this->get('/api/auth/google/callback')->assertRedirect('/account');
        $this->assertAuthenticated();
    }

    /**
     * Заблокированный пользователь не входит через соцсеть.
     */
    public function test_banned_user_cannot_sign_in_with_social_network(): void
    {
        $this->enable('vkid');
        $user = $this->createUser('banned@example.test');
        $user->ban();
        $this->em->flush();
        $this->fakeSocialite('vkid', 'vk-1', 'banned@example.test', 'X');

        $this->get('/api/auth/vkid/callback')->assertRedirect('/login?social_error=1');
        $this->assertGuest();
    }

    /**
     * Данные Telegram проверяются подписью и сроком; без почты аккаунт создаётся после её ввода.
     */
    public function test_telegram_login_is_verified_and_asks_for_email(): void
    {
        $data = ['id' => '777', 'first_name' => 'Олег', 'username' => 'oleg', 'auth_date' => (string) time()];
        $this->postJson('/api/auth/telegram', ['data' => $this->signTelegram($data)])->assertNotFound();
        $this->getJson('/api/auth/social/pending')->assertNotFound();
        $this->enableTelegram();

        $this->postJson('/api/auth/telegram', ['data' => [...$data, 'hash' => str_repeat('0', 64)]])
            ->assertJsonValidationErrors('data');
        $this->postJson('/api/auth/telegram', ['data' => $this->signTelegram($data)])
            ->assertOk()
            ->assertJsonPath('next', 'pending');
        $this->getJson('/api/auth/social/pending')
            ->assertJson(['provider' => 'telegram', 'name' => 'Олег', 'email' => null]);
        $this->postJson('/api/auth/social/complete', [
            'consents' => ['personal_data' => true, 'site_rules' => true, 'age' => true],
        ])->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/social/complete', [
            'email' => 'oleg@example.test',
            'consents' => ['personal_data' => true, 'site_rules' => true, 'age' => true],
        ])->assertCreated();
        $this->getJson('/api/session')->assertJsonPath('user.email_verified', false);

        $old = $this->signTelegram([...$data, 'auth_date' => (string) (time() - 2 * 86400)]);
        $this->postJson('/api/logout');
        $this->postJson('/api/auth/telegram', ['data' => $old])->assertUnprocessable();
    }

    /**
     * Заполняет заглушки правовых документов: без согласий регистрация через соцсеть невозможна.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLegalDocuments();
    }

    /**
     * Включает провайдера OAuth с тестовыми ключами; остальные настройки группы сохраняются.
     */
    private function enable(string $provider): void
    {
        $this->updateSettings(SettingGroup::Social, [
            "social__{$provider}__enabled" => '1',
            "social__{$provider}__client_id" => "app-{$provider}",
            "social__{$provider}__client_secret" => 'secret',
        ]);
    }

    /**
     * Включает Telegram с тестовым токеном бота.
     */
    private function enableTelegram(): void
    {
        $this->updateSettings(SettingGroup::Social, [
            'social__telegram__enabled' => '1', 'social__telegram__bot_token' => self::BOT_TOKEN,
        ]);
    }

    /**
     * Подменяет Socialite: драйвер провайдера возвращает пользователя с заданными данными.
     */
    private function fakeSocialite(string $provider, string $id, ?string $email, ?string $name): void
    {
        $user = (new SocialiteUser())->map(['id' => $id, 'email' => $email, 'name' => $name]);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($user);
        $factory = Mockery::mock(Socialite::class);
        $factory->shouldReceive('driver')->with($provider)->andReturn($driver);
        $this->app->instance(Socialite::class, $factory);
    }

    /**
     * Подписывает данные входа Telegram тестовым токеном по правилам Telegram Login.
     *
     * @param array<string, string> $data
     *
     * @return array<string, string>
     */
    private function signTelegram(array $data): array
    {
        ksort($data);
        $check = implode("\n", array_map(
            static fn (string $key, string $value): string => $key . '=' . $value,
            array_keys($data),
            $data,
        ));

        return [...$data, 'hash' => hash_hmac('sha256', $check, hash('sha256', self::BOT_TOKEN, binary: true))];
    }
}
