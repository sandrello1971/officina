<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BusinessCardController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Pagine pubbliche
Route::get('/', [PageController::class, 'index'])->name('home');
Route::get('/profilum-societatis', [PageController::class, 'profilumSocietatis'])->name('profilum');
Route::get('/fundamenta', [PageController::class, 'fundamenta'])->name('fundamenta');
Route::get('/methodus', [PageController::class, 'methodus'])->name('methodus');
Route::get('/valor', [PageController::class, 'valor'])->name('valor');
Route::get('/atheneum', [PageController::class, 'atheneum'])->name('atheneum');
Route::get('/chi-siamo', [PageController::class, 'chiSiamo'])->name('chi-siamo');
Route::get('/servizi', [PageController::class, 'servizi'])->name('servizi');
Route::get('/percorsi', [PageController::class, 'percorsi'])->name('percorsi');
Route::get('/risorse', [PageController::class, 'risorse'])->name('risorse');
Route::get('/contatti', [PageController::class, 'contatti'])->name('contatti');
Route::get('/contactus', [PageController::class, 'contactus'])->name('contactus');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy');
Route::get('/cookie-policy', [PageController::class, 'cookiePolicy'])->name('cookies');
Route::get('/jooice', [PageController::class, 'jooiceLanding'])->name('jooice');

// Blog
Route::get('/commentarium', [BlogController::class, 'index'])->name('commentarium.index');
Route::get('/commentarium/{post:slug}', [BlogController::class, 'show'])->name('commentarium.show');

// Business card digitale
Route::get('/card/{username}', [BusinessCardController::class, 'show'])->name('card.show');
Route::get('/card/{username}/vcard', [BusinessCardController::class, 'vcard'])->name('card.vcard');

// Admin login
Route::get('/nosciteadmin/auth', [Admin\AdminController::class, 'loginForm'])->name('admin.login');
Route::post('/nosciteadmin/auth', [Admin\AdminController::class, 'login'])->name('admin.login.submit');

// Admin (protetto da auth + ruolo admin)
Route::prefix('nosciteadmin')->middleware(['auth', 'role:admin'])->name('admin.')->group(function () {
    Route::get('/', [Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/contacts', [Admin\AdminController::class, 'contacts'])->name('contacts');
    Route::get('/blog/new', function () {
        return view('admin.blog-editor');
    })->name('blog.new');
    Route::get('/blog/{postId}/edit', function ($postId) {
        return view('admin.blog-editor', ['postId' => $postId]);
    })->name('blog.edit');
});

// Profilo utente (Breeze)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Sitemap
Route::get('/sitemap.xml', function () {
    return response()->file(public_path('sitemap.xml'), ['Content-Type' => 'application/xml']);
});

// Auth (Breeze)
require __DIR__.'/auth.php';
