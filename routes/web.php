<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('home');
Route::get('/primus', [PageController::class, 'primus'])->name('primus');
Route::get('/consilium', [PageController::class, 'consilium'])->name('consilium');
Route::get('/initium', [PageController::class, 'initium'])->name('initium');
Route::get('/structura', [PageController::class, 'structura'])->name('structura');
Route::get('/ai-agents-mcp', [PageController::class, 'aiAgentsMcp'])->name('ai-agents-mcp');
Route::get('/risorse', [PageController::class, 'risorse'])->name('risorse');
Route::get('/contatti', [PageController::class, 'contatti'])->name('contatti');
Route::post('/contatti', [PageController::class, 'contatti'])->name('contatti.post');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy');
Route::get('/cookie-policy', [PageController::class, 'cookiePolicy'])->name('cookies');

Route::get('/sitemap.xml', function () {
    return response()->file(public_path('sitemap.xml'), ['Content-Type' => 'application/xml']);
});

// ===== AREA STUDENTI =====
Route::prefix('learn')->name('student.')->group(function () {
    Route::get('/login', [App\Http\Controllers\Student\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\Student\AuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [App\Http\Controllers\Student\AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password', [App\Http\Controllers\Student\AuthController::class, 'showChangePassword'])->name('change-password');
    Route::post('/change-password', [App\Http\Controllers\Student\AuthController::class, 'changePassword'])->name('change-password.post');

    Route::middleware(['student.auth', 'student.password'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/course/{course:slug}', [App\Http\Controllers\Student\CourseController::class, 'show'])->name('course.show');
        Route::get('/course/{course:slug}/module/{module}', [App\Http\Controllers\Student\CourseController::class, 'module'])->name('module.show');
        Route::post('/course/{course:slug}/module/{module}/complete', [App\Http\Controllers\Student\CourseController::class, 'completeModule'])->name('module.complete');
        Route::get('/course/{course:slug}/module/{module}/canvas/{canvas}', [App\Http\Controllers\Student\CourseController::class, 'canvas'])->name('module.canvas');

        Route::get('/quiz/{quiz}', [App\Http\Controllers\Student\QuizController::class, 'show'])->name('quiz.show');
        Route::post('/quiz/{quiz}/start', [App\Http\Controllers\Student\QuizController::class, 'start'])->name('quiz.start');
        Route::post('/quiz/{quiz}/submit', [App\Http\Controllers\Student\QuizController::class, 'submit'])->name('quiz.submit');
        Route::get('/quiz/{quiz}/result/{attempt}', [App\Http\Controllers\Student\QuizController::class, 'result'])->name('quiz.result');

        Route::get('/chat/{course:slug}', [App\Http\Controllers\Student\ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat/message', [App\Http\Controllers\Student\ChatController::class, 'sendMessage'])->name('chat.message');
    });
});

// ===== AREA ADMIN ATHENEUM =====
Route::prefix('admin')->name('admin.')->middleware(['admin.auth'])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('courses', App\Http\Controllers\Admin\CourseController::class);
    Route::resource('courses.modules', App\Http\Controllers\Admin\ModuleController::class);
    Route::resource('courses.modules.materials', App\Http\Controllers\Admin\MaterialController::class);

    Route::resource('students', App\Http\Controllers\Admin\StudentController::class);
    Route::post('students/{student}/courses', [App\Http\Controllers\Admin\StudentController::class, 'assignCourse'])->name('students.assign-course');
    Route::delete('students/{student}/courses/{course}', [App\Http\Controllers\Admin\StudentController::class, 'removeCourse'])->name('students.remove-course');
    Route::post('students/{student}/send-credentials', [App\Http\Controllers\Admin\StudentController::class, 'sendCredentials'])->name('students.send-credentials');

    Route::resource('quizzes', App\Http\Controllers\Admin\QuizController::class);
    Route::resource('quizzes.questions', App\Http\Controllers\Admin\QuizQuestionController::class);
    Route::get('quizzes/{quiz}/results', [App\Http\Controllers\Admin\QuizController::class, 'results'])->name('quizzes.results');

    Route::get('rag', [App\Http\Controllers\Admin\RagController::class, 'index'])->name('rag.index');
    Route::post('rag/upload', [App\Http\Controllers\Admin\RagController::class, 'upload'])->name('rag.upload');
    Route::delete('rag/{document}', [App\Http\Controllers\Admin\RagController::class, 'destroy'])->name('rag.destroy');

    Route::get('analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics');

    Route::post('upload-image', [App\Http\Controllers\Admin\AdminDashboardController::class, 'uploadImage'])->name('upload-image');
    Route::post('courses/{course}/generate-quiz', [App\Http\Controllers\Admin\CourseController::class, 'generateQuiz'])->name('courses.generate-quiz');

    Route::get('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'showLogin'])->name('login')->withoutMiddleware(['admin.auth']);
    Route::post('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'login'])->name('login.post')->withoutMiddleware(['admin.auth']);
    Route::post('/logout', [App\Http\Controllers\Admin\AdminAuthController::class, 'logout'])->name('logout');
});
