<?php

declare(strict_types=1);

use App\Entities\Language;
use App\Enums\Http\RateLimit;
use App\Enums\Reaction\ReactionType;
use App\Enums\Settings\SettingGroup;
use App\Enums\Social\SocialProvider;
use App\Enums\User\Ability;
use App\Http\Controllers\AdminAlbumController;
use App\Http\Controllers\AdminArticleController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleSectionController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LocalizationController;
use App\Http\Controllers\MyArticleController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFilesController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureConsentsAccepted;
use App\Services\User\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$languageCode = Language::CODE_REGEX;

Route::prefix('admin/content')
    ->middleware(['auth', 'can:' . Ability::AccessAdmin->value, RateLimit::Interact->middleware()])
    ->group(function () use ($languageCode): void {
        Route::post('/articles', [AdminArticleController::class, 'store'])
            ->middleware(EnsureConsentsAccepted::class)
            ->name('admin.articles.store');
        Route::post('/articles/moderate', [AdminArticleController::class, 'moderate'])->name('admin.articles.moderate');
        Route::put('/articles/{article}', [AdminArticleController::class, 'update'])
            ->whereNumber('article')
            ->name('admin.articles.update');
        Route::delete('/articles/{article}', [AdminArticleController::class, 'destroy'])
            ->whereNumber('article')
            ->name('admin.articles.destroy');
        Route::post('/articles/{article}/publish', [AdminArticleController::class, 'publish'])
            ->whereNumber('article')
            ->name('admin.articles.publish');
        Route::post('/sections', [ArticleSectionController::class, 'store'])->name('admin.sections.store');
        Route::put('/sections/{section}', [ArticleSectionController::class, 'update'])
            ->whereNumber('section')
            ->name('admin.sections.update');
        Route::delete('/sections/{section}', [ArticleSectionController::class, 'destroy'])
            ->whereNumber('section')
            ->name('admin.sections.destroy');
        Route::post('/albums/moderate', [AdminAlbumController::class, 'moderate'])->name('admin.albums.moderate');
        Route::post('/complaints/resolve', [ComplaintController::class, 'resolve'])->name('admin.complaints.resolve');
        Route::post('/users/{user}/roles', [AdminUserController::class, 'role'])
            ->whereNumber('user')
            ->name('admin.users.role');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])
            ->whereNumber('user')
            ->name('admin.users.update');
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])
            ->whereNumber('user')
            ->name('admin.users.ban');
        Route::post('/users/{user}/unban', [AdminUserController::class, 'unban'])
            ->whereNumber('user')
            ->name('admin.users.unban');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])
            ->whereNumber('user')
            ->name('admin.users.destroy');
        Route::middleware('can:' . Ability::ManageSettings->value)->group(function () use ($languageCode): void {
            Route::post('/legal-documents', [LegalDocumentController::class, 'store'])
                ->name('admin.legal-documents.store');
            Route::post('/settings/mail/test', [SettingsController::class, 'testMail'])
                ->middleware(RateLimit::Heavy->middleware())
                ->name('admin.settings.mail-test');
            Route::post('/settings/seo/sitemap', [SettingsController::class, 'regenerateSitemap'])
                ->middleware(RateLimit::Heavy->middleware())
                ->name('admin.settings.sitemap');
            Route::put('/settings/{group}', [SettingsController::class, 'update'])
                ->whereIn('group', SettingGroup::values()->all())
                ->name('admin.settings.update');
            Route::post('/languages', [LanguageController::class, 'store'])->name('admin.languages.store');
            Route::put('/languages/{language}', [LanguageController::class, 'update'])
                ->where('language', $languageCode)
                ->name('admin.languages.update');
            Route::delete('/languages/{language}', [LanguageController::class, 'destroy'])
                ->where('language', $languageCode)
                ->name('admin.languages.destroy');
        });
    });

Route::middleware(RateLimit::Read->middleware())->group(function (): void {
    Route::get('/robots.txt', [PublicFilesController::class, 'robots']);
    Route::get('/sitemap.xml', [PublicFilesController::class, 'sitemap'])->name('sitemap');
    Route::get('/rss.xml', [PublicFilesController::class, 'rss'])->can(Ability::ReadFeed);
});

Route::prefix('api')->group(function () use ($languageCode): void {
    Route::get('/i18n', [LocalizationController::class, 'config']);
    Route::get('/i18n/{code}', [LocalizationController::class, 'messages'])->where('code', $languageCode);
    Route::get('/legal', [LegalController::class, 'terms']);
    Route::get('/session', [ProfileController::class, 'session']);
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->whereNumber('id')
        ->where('hash', '[a-f0-9]{' . EmailVerificationService::HASH_LENGTH . '}')
        ->middleware(['signed', RateLimit::Create->middleware()])
        ->name('verification.verify');
    Route::get('/authors/{user}', [ProfileController::class, 'author'])
        ->whereNumber('user')
        ->can('viewProfile', 'user');
    Route::get('/sections', [ArticleSectionController::class, 'index']);
    Route::get('/sections/filter', [ArticleSectionController::class, 'filter']);
    Route::get('/csrf', static fn (Request $request): JsonResponse => new JsonResponse([
        'token' => $request->session()->token(),
    ]));
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{article}', [ArticleController::class, 'show'])
        ->whereNumber('article')
        ->can('view', 'article');
    Route::get('/tags', [ArticleController::class, 'tags'])->middleware(RateLimit::Read->middleware());
    Route::get('/head', [SeoController::class, 'home']);
    Route::get('/site', [SeoController::class, 'site']);
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->whereUuid('album')->can('view', 'album');
    Route::post('/articles/{article}/view', [ArticleController::class, 'view'])
        ->whereNumber('article')
        ->middleware(RateLimit::Read->middleware());
    Route::get('/articles/{article}/comments', [CommentController::class, 'index'])
        ->whereNumber('article')
        ->middleware(RateLimit::Read->middleware())
        ->can('interact', 'article');
    Route::get('/comments/{comment}/replies', [CommentController::class, 'replies'])
        ->whereNumber('comment')
        ->middleware(RateLimit::Read->middleware())
        ->can('viewReplies', 'comment');
    Route::get('/polls/{poll}/options/{option}/voters', [PollController::class, 'voters'])
        ->whereUuid('poll')
        ->whereNumber('option')
        ->middleware(RateLimit::Read->middleware())
        ->can('viewVoters', 'poll');
    Route::get('/auth/providers', [SocialAuthController::class, 'index']);
    Route::middleware(['guest', RateLimit::Edit->middleware()])->group(function (): void {
        Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->whereIn('provider', SocialProvider::oauth()->all())
            ->can(Ability::OAuthLogin, 'provider')
            ->name('social.redirect');
        Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
            ->whereIn('provider', SocialProvider::oauth()->all())
            ->can(Ability::OAuthLogin, 'provider')
            ->name('social.callback');
        Route::post('/auth/telegram', [SocialAuthController::class, 'telegram'])->can(Ability::TelegramLogin);
        Route::get('/auth/social/pending', [SocialAuthController::class, 'pending']);
        Route::post('/auth/social/complete', [SocialAuthController::class, 'complete']);
    });
    Route::middleware(['guest', RateLimit::Create->middleware()])->group(function (): void {
        Route::post('/register', [UserController::class, 'register']);
        Route::post('/login', [UserController::class, 'login']);
    });
    Route::middleware('auth')->group(function (): void {
        Route::post('/articles', [ArticleController::class, 'store'])
            ->middleware([RateLimit::Create->middleware(), EnsureConsentsAccepted::class]);
        Route::get('/my/articles', [MyArticleController::class, 'index']);
        Route::get('/my/articles/{article}', [MyArticleController::class, 'edit'])
            ->whereNumber('article')
            ->can('revise', 'article');
        Route::put('/my/articles/{article}', [MyArticleController::class, 'update'])
            ->whereNumber('article')
            ->middleware([RateLimit::Edit->middleware(), EnsureConsentsAccepted::class])
            ->can('revise', 'article');
        Route::delete('/my/articles/{article}', [MyArticleController::class, 'destroy'])
            ->whereNumber('article')
            ->middleware(RateLimit::Create->middleware())
            ->can('revise', 'article');
        Route::post('/images', [ImageController::class, 'store'])
            ->middleware([RateLimit::Interact->middleware(), EnsureConsentsAccepted::class]);
        Route::post('/charts', [ChartController::class, 'store'])
            ->middleware([RateLimit::Interact->middleware(), EnsureConsentsAccepted::class]);
        Route::get('/charts/{chart}', [ChartController::class, 'show'])->whereUuid('chart')->can('view', 'chart');
        Route::get('/my/albums', [AlbumController::class, 'mine']);
        Route::post('/complaints', [ComplaintController::class, 'store'])->middleware(RateLimit::Create->middleware());
        Route::post('/articles/{article}/comments', [CommentController::class, 'store'])
            ->whereNumber('article')
            ->middleware([RateLimit::Create->middleware(), EnsureConsentsAccepted::class])
            ->can('interact', 'article');
        Route::put('/comments/{comment}', [CommentController::class, 'update'])
            ->whereNumber('comment')
            ->middleware([RateLimit::Edit->middleware(), EnsureConsentsAccepted::class])
            ->can('update', 'comment');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])
            ->whereNumber('comment')
            ->middleware(RateLimit::Edit->middleware())
            ->can('delete', 'comment');
        Route::match(
            ['put', 'delete'],
            '/articles/{article}/reactions/{type}',
            [CommentController::class, 'reactToArticle'],
        )
            ->whereNumber('article')
            ->whereIn('type', ReactionType::cases())
            ->middleware(RateLimit::Read->middleware())
            ->can('react', 'article');
        Route::match(
            ['put', 'delete'],
            '/comments/{comment}/reactions/{type}',
            [CommentController::class, 'reactToComment'],
        )
            ->whereNumber('comment')
            ->whereIn('type', ReactionType::cases())
            ->middleware(RateLimit::Read->middleware())
            ->can('react', 'comment');
        Route::post('/albums', [AlbumController::class, 'store'])
            ->middleware([RateLimit::Create->middleware(), EnsureConsentsAccepted::class]);
        Route::put('/albums/{album}', [AlbumController::class, 'update'])
            ->whereUuid('album')
            ->middleware([RateLimit::Edit->middleware(), EnsureConsentsAccepted::class])
            ->can('update', 'album');
        Route::delete('/albums/{album}', [AlbumController::class, 'destroy'])
            ->whereUuid('album')
            ->middleware(RateLimit::Create->middleware())
            ->can('update', 'album');
        Route::post('/polls/{poll}/votes', [PollController::class, 'vote'])
            ->whereUuid('poll')
            ->middleware(RateLimit::Interact->middleware())
            ->can('vote', 'poll');
        Route::delete('/polls/{poll}/votes', [PollController::class, 'retract'])
            ->whereUuid('poll')
            ->middleware(RateLimit::Interact->middleware())
            ->can('vote', 'poll');
        Route::get('/user/consents', [LegalController::class, 'status']);
        Route::post('/user/consents', [LegalController::class, 'update'])->middleware(RateLimit::Edit->middleware());
        Route::get('/user/export', [LegalController::class, 'export'])
            ->middleware(RateLimit::Heavy->middleware())
            ->can(Ability::ExportData);
        Route::get('/user', [ProfileController::class, 'show']);
        Route::put('/user/profile', [ProfileController::class, 'update'])->middleware(RateLimit::Edit->middleware());
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware(RateLimit::Mail->middleware());
        Route::post('/user/avatar', [ProfileController::class, 'avatar'])->middleware(RateLimit::Create->middleware());
        Route::delete('/user/avatar', [ProfileController::class, 'removeAvatar'])
            ->middleware(RateLimit::Create->middleware());
        Route::post('/logout', [UserController::class, 'logout']);
        Route::put('/user', [UserController::class, 'update'])->middleware(RateLimit::Edit->middleware());
        Route::delete('/user', [UserController::class, 'delete'])->middleware(RateLimit::Create->middleware());
    });
});
