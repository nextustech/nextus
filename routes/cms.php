<?php

use App\Http\Controllers\MediaLibraryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\WebsitePageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/branding', [ThemeController::class, 'show'])->name('branding.show');
    Route::put('/branding', [ThemeController::class, 'update'])->name('branding.update');
    Route::post('/branding/publish', [ThemeController::class, 'publish'])->name('branding.publish');
    Route::post('/branding/reset', [ThemeController::class, 'reset'])->name('branding.reset');
    Route::post('/branding/template', [ThemeController::class, 'switchTemplate'])->name('branding.template');
    Route::get('/branding/preview-css', [ThemeController::class, 'previewCss'])->name('branding.preview-css');
    Route::get('/cms/pages', [WebsitePageController::class, 'index'])->name('pages.index');
    Route::get('/cms/menus', [MenuController::class, 'index'])->name('menus.index');
    Route::post('/cms/menus', [MenuController::class, 'store'])->name('menus.store');
    Route::put('/cms/menus/{area}/order', [MenuController::class, 'reorder'])->name('menus.reorder');
    Route::patch('/cms/menus/{id}', [MenuController::class, 'update'])->name('menus.update');
    Route::delete('/cms/menus/{id}', [MenuController::class, 'destroy'])->name('menus.destroy');
    Route::get('/cms/media', [MediaLibraryController::class, 'index'])->name('media.index');
    Route::post('/cms/media', [MediaLibraryController::class, 'store'])->name('media.store');
    Route::patch('/cms/media/{id}', [MediaLibraryController::class, 'update'])->name('media.update');
    Route::delete('/cms/media/{id}', [MediaLibraryController::class, 'destroy'])->name('media.destroy');
    Route::get('/cms/pages/create', [WebsitePageController::class, 'create'])->name('pages.create');
    Route::post('/cms/pages', [WebsitePageController::class, 'store'])->name('pages.store');
    Route::get('/cms/pages/{id}/edit', [WebsitePageController::class, 'edit'])->name('pages.edit');
    Route::patch('/cms/pages/{id}', [WebsitePageController::class, 'update'])->name('pages.update');
    Route::post('/cms/pages/{id}/publish', [WebsitePageController::class, 'publish'])->name('pages.publish');
    Route::post('/cms/pages/{id}/unpublish', [WebsitePageController::class, 'unpublish'])->name('pages.unpublish');
    Route::delete('/cms/pages/{id}', [WebsitePageController::class, 'destroy'])->name('pages.destroy');
    Route::put('/cms/pages/{id}/sections/order', [WebsitePageController::class, 'reorder'])->name('pages.sections.order');
    Route::post('/cms/pages/{id}/revisions/{revisionId}/restore', [WebsitePageController::class, 'restore'])->name('pages.revisions.restore');
});

Route::get('/preview/pages/{id}', [WebsitePageController::class, 'preview'])->name('pages.preview');
Route::get('/theme.css', [StorefrontController::class, 'themeCss'])->name('storefront.theme-css');
Route::get('/robots.txt', [StorefrontController::class, 'robots'])->name('storefront.robots');
Route::get('/sitemap.xml', [StorefrontController::class, 'sitemap'])->name('storefront.sitemap');
Route::get('/products/{slug}/media/{mediaId}', [StorefrontController::class, 'productMedia'])
    ->whereNumber('mediaId')
    ->name('storefront.product-media');
Route::get('/media/{id}', [MediaLibraryController::class, 'show'])->whereNumber('id')->name('storefront.media');
Route::get('/{path?}', [StorefrontController::class, 'show'])
    ->where('path', '.*')
    ->name('storefront.page');
