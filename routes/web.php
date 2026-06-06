<?php

use App\Http\Controllers\Admin\MicrosoftAuthController as AdminMicrosoftAuthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Student\MicrosoftAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('home');
Route::get('/primus', [PageController::class, 'primus'])->name('primus');
Route::get('/consilium', [PageController::class, 'consilium'])->name('consilium');
Route::get('/initium', [PageController::class, 'initium'])->name('initium');
Route::get('/structura', [PageController::class, 'structura'])->name('structura');
Route::get('/ai-agents-mcp', [PageController::class, 'aiAgentsMcp'])->name('ai-agents-mcp');
Route::get('/conformita-ai-act', [PageController::class, 'conformitaAiAct'])->name('conformita-ai-act');
Route::get('/risorse', [PageController::class, 'risorse'])->name('risorse');
Route::get('/contatti', [PageController::class, 'contatti'])->name('contatti');
Route::post('/contatti', [PageController::class, 'contatti'])->name('contatti.post');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy');
Route::get('/cookie-policy', [PageController::class, 'cookiePolicy'])->name('cookies');

Route::get('/mappa-percorso', [PageController::class, 'mappaPercorso'])->name('lead-magnet.show');
Route::get('/mappa-percorso/grazie', [PageController::class, 'mappaPercorsoGrazie'])->name('lead-magnet.thank-you');

Route::get('/sitemap.xml', function () {
    return response()->file(public_path('sitemap.xml'), ['Content-Type' => 'application/xml']);
});

// Verifica pubblica del certificato — fuori da student.auth, accessibile a chiunque
// abbia il codice. Rate-limit per-IP definito in AppServiceProvider.
Route::get('/certificato/verifica/{code}', [App\Http\Controllers\CertificateVerifyController::class, 'show'])
    ->middleware('throttle:certificate-verify')
    ->name('certificate.verify');

Route::get('/certificato/verifica/{code}/pdf', [App\Http\Controllers\CertificateVerifyController::class, 'downloadSigned'])
    ->middleware('throttle:certificate-verify')
    ->name('certificate.verify.pdf');

// ===== AREA STUDENTI =====
Route::prefix('learn')->name('student.')->group(function () {
    Route::get('/demo', [App\Http\Controllers\Student\DemoController::class, 'start'])->name('demo.start');
    Route::get('/login', [App\Http\Controllers\Student\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\Student\AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
    Route::post('/logout', [App\Http\Controllers\Student\AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password', [App\Http\Controllers\Student\AuthController::class, 'showChangePassword'])->name('change-password');
    Route::post('/change-password', [App\Http\Controllers\Student\AuthController::class, 'changePassword'])->name('change-password.post');

    // Iscrizione a una classe con codice (pacchetto 3). Pubblico: gestisce sia lo
    // studente loggato sia la registrazione di un nuovo studente via codice.
    Route::get('/classi/unisciti', [App\Http\Controllers\Student\ClassJoinController::class, 'create'])->name('classes.join.create');
    Route::post('/classi/unisciti', [App\Http\Controllers\Student\ClassJoinController::class, 'store'])
        ->middleware('throttle:class-join')->name('classes.join.store');

    Route::middleware(['student.auth', 'student.password', 'demo.restrictions'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/classi', [App\Http\Controllers\Student\StudentClassController::class, 'index'])->name('classes.index');
        Route::get('/course/{course:slug}', [App\Http\Controllers\Student\CourseController::class, 'show'])->name('course.show');
        Route::get('/course/{course:slug}/module/{module}', [App\Http\Controllers\Student\CourseController::class, 'module'])->name('module.show');
        Route::post('/course/{course:slug}/module/{module}/complete', [App\Http\Controllers\Student\CourseController::class, 'completeModule'])->name('module.complete');
        Route::get('/course/{course:slug}/module/{module}/canvas/{canvas}', [App\Http\Controllers\Student\CourseController::class, 'canvas'])->name('module.canvas');

        // Mappe concettuali a livello corso (lato studente)
        Route::get('/course/{course:slug}/concept-maps', [App\Http\Controllers\Student\ConceptMapController::class, 'index'])->name('course.concept-maps.index');
        Route::get('/course/{course:slug}/concept-map/{concept_map}', [App\Http\Controllers\Student\ConceptMapController::class, 'show'])->name('course.concept-map.show');
        Route::post('/course/{course:slug}/concept-map/{concept_map}/fork', [App\Http\Controllers\Student\ConceptMapController::class, 'fork'])->name('course.concept-map.fork');
        Route::get('/course/{course:slug}/concept-map/{concept_map}/my', [App\Http\Controllers\Student\ConceptMapController::class, 'editFork'])->name('course.concept-map.my');
        Route::patch('/course/{course:slug}/concept-map/{concept_map}/my', [App\Http\Controllers\Student\ConceptMapController::class, 'saveFork'])->name('course.concept-map.my.save');

        Route::get('/quiz/{quiz}', [App\Http\Controllers\Student\QuizController::class, 'show'])->name('quiz.show');
        Route::post('/quiz/{quiz}/start', [App\Http\Controllers\Student\QuizController::class, 'start'])->name('quiz.start');
        Route::post('/quiz/{quiz}/submit', [App\Http\Controllers\Student\QuizController::class, 'submit'])
            ->middleware('throttle:5,1')
            ->name('quiz.submit');
        Route::post('/quiz/{quiz}/abandon', [App\Http\Controllers\Student\QuizController::class, 'abandon'])->name('quiz.abandon');
        Route::get('/quiz/{quiz}/result/{attempt}', [App\Http\Controllers\Student\QuizController::class, 'result'])->name('quiz.result');

        Route::get('/chat/{course:slug}', [App\Http\Controllers\Student\ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat/message', [App\Http\Controllers\Student\ChatController::class, 'sendMessage'])->middleware('throttle:minerva-chat')->name('chat.message');
        Route::post('/minerva/ask', [App\Http\Controllers\Student\ChatController::class, 'minervaAsk'])->middleware('throttle:minerva-chat')->name('minerva.ask');

        Route::get('/certificate/{course:slug}', [App\Http\Controllers\Student\CertificateController::class, 'download'])->name('certificate.download');
        Route::get('/certificate/{course:slug}/view', [App\Http\Controllers\Student\CertificateController::class, 'show'])->name('certificate.show');

        Route::post('/notes/{module}', [App\Http\Controllers\Student\NoteController::class, 'save'])->name('notes.save');
        Route::get('/notes/{module}', [App\Http\Controllers\Student\NoteController::class, 'list'])->name('notes.list');
        Route::delete('/notes/{note}', [App\Http\Controllers\Student\NoteController::class, 'delete'])->name('notes.delete');

        Route::get('/canvas/{material}/data', [App\Http\Controllers\Student\CanvasController::class, 'getData'])->name('canvas.get');
        Route::patch('/canvas/{material}/data', [App\Http\Controllers\Student\CanvasController::class, 'saveData'])->name('canvas.save');

        Route::get('/material/{material}/download', [App\Http\Controllers\Student\MaterialController::class, 'download'])->name('material.download');
        Route::get('/material/{material}/canvas', [App\Http\Controllers\Student\MaterialController::class, 'canvas'])->name('material.canvas');

        Route::get('/course/{course:slug}/instructor/{material}', [App\Http\Controllers\Student\InstructorMaterialController::class, 'show'])->name('instructor.material.show');
        Route::get('/course/{course:slug}/instructor/{material}/download', [App\Http\Controllers\Student\InstructorMaterialController::class, 'download'])->name('instructor.material.download');

        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/',                    [App\Http\Controllers\Student\DocumentController::class, 'index'])->name('index');
            Route::post('/',                   [App\Http\Controllers\Student\DocumentController::class, 'store'])->name('store');
            Route::get('/{document}/download', [App\Http\Controllers\Student\DocumentController::class, 'download'])->name('download');
            Route::put('/{document}',          [App\Http\Controllers\Student\DocumentController::class, 'update'])->name('update');
            Route::delete('/{document}',       [App\Http\Controllers\Student\DocumentController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('docenti/documenti')->name('instructor_documents.')->group(function () {
            Route::get('/',                    [App\Http\Controllers\Student\InstructorSharedDocumentController::class, 'index'])->name('index');
            Route::get('/{document}/download', [App\Http\Controllers\Student\InstructorSharedDocumentController::class, 'download'])->name('download');
        });

        Route::prefix('knowledge-base')->name('knowledge_base.')->group(function () {
            Route::get('/', [App\Http\Controllers\Student\InstructorNoteController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Student\InstructorNoteController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Student\InstructorNoteController::class, 'store'])->name('store');

            Route::post('/upload-image', [App\Http\Controllers\Student\InstructorNoteController::class, 'uploadImage'])->name('upload-image');
            Route::get('/tag-suggest', [App\Http\Controllers\Student\InstructorNoteController::class, 'tagSuggest'])->name('tag-suggest');
            Route::get('/modules/{courseId}', [App\Http\Controllers\Student\InstructorNoteController::class, 'modulesByCourse'])->name('modules');
            Route::get('/sections/{courseId}', [App\Http\Controllers\Student\InstructorNoteController::class, 'sectionsByCourse'])->name('sections');

            Route::get('/{note}/edit', [App\Http\Controllers\Student\InstructorNoteController::class, 'edit'])->name('edit');
            Route::put('/{note}', [App\Http\Controllers\Student\InstructorNoteController::class, 'update'])->name('update');
            Route::delete('/{note}', [App\Http\Controllers\Student\InstructorNoteController::class, 'destroy'])->name('destroy');
            Route::post('/{note}/restore', [App\Http\Controllers\Student\InstructorNoteController::class, 'restore'])->name('restore');
        });

        Route::get('/video/{videoId}/stream', [App\Http\Controllers\Student\VideoController::class, 'stream'])->name('video.stream');
        Route::get('/video/{videoId}/thumbnail', [App\Http\Controllers\Student\VideoController::class, 'thumbnail'])->name('video.thumbnail');
        Route::post('/video/{videoId}/chat', [App\Http\Controllers\Student\VideoController::class, 'chat'])->name('video.chat');
        Route::get('/video/{videoId}/transcript', [App\Http\Controllers\Student\VideoController::class, 'transcript'])->name('video.transcript');
        Route::get('/video/{videoId}/status', [App\Http\Controllers\Student\VideoController::class, 'status'])->name('video.status');
        Route::get('/course/{course:slug}/video-search', [App\Http\Controllers\Student\VideoController::class, 'searchInCourse'])->name('video.search.course');
        Route::get('/course/{course:slug}/module/{module}/video-search', [App\Http\Controllers\Student\VideoController::class, 'searchInModule'])->name('video.search.module');

        // Impostazioni formatore (Fase D) — toggle accepts_dm per corso
        Route::get('/formatore/impostazioni', [App\Http\Controllers\Student\InstructorSettingsController::class, 'index'])->name('instructor_settings.index');
        Route::patch('/formatore/impostazioni/dm', [App\Http\Controllers\Student\InstructorSettingsController::class, 'updateDm'])->name('instructor_settings.updateDm');

        // Annunci (broadcast 1-to-many formatore → studenti corso)
        Route::get('/annunci',              [App\Http\Controllers\Student\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/annunci/nuovo',        [App\Http\Controllers\Student\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/annunci',             [App\Http\Controllers\Student\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/annunci/{announcement}', [App\Http\Controllers\Student\AnnouncementController::class, 'show'])->name('announcements.show');

        // Messaggi (DM) — backend Fase A (UI Fase B, Reverb Fase C)
        Route::prefix('messaggi')->name('messages.')->group(function () {
            Route::get('/',                          [App\Http\Controllers\Student\ConversationController::class, 'index'])->name('index');
            Route::get('/nuovo',                     [App\Http\Controllers\Student\ConversationController::class, 'create'])->name('create');
            Route::post('/',                         [App\Http\Controllers\Student\ConversationController::class, 'store'])->name('store');
            Route::get('/{conversation}',            [App\Http\Controllers\Student\ConversationController::class, 'show'])->name('show');
            Route::patch('/{conversation}/letto',    [App\Http\Controllers\Student\ConversationController::class, 'markRead'])->name('markRead');
            Route::post('/{conversation}/messaggi',  [App\Http\Controllers\Student\MessageController::class, 'store'])->name('messages.store');
        });
    });
});

// ===== MICROSOFT ENTRA ID SSO (pubbliche, fuori da student.auth) =====
Route::prefix('auth/microsoft')->group(function () {
    Route::get('/', [MicrosoftAuthController::class, 'redirect'])
        ->name('student.microsoft.redirect');
    Route::get('/callback', [MicrosoftAuthController::class, 'callback'])
        ->name('student.microsoft.callback');
});

// ===== ADMIN MICROSOFT SSO (pubbliche, fuori da admin.auth) =====
Route::prefix('admin/auth/microsoft')->group(function () {
    Route::get('/', [AdminMicrosoftAuthController::class, 'redirect'])
        ->name('admin.microsoft.redirect');
    Route::get('/callback', [AdminMicrosoftAuthController::class, 'callback'])
        ->name('admin.microsoft.callback');
});

// ===== AREA DOCENTE SCHOLA =====
// Auth via sessione studente + gate professor. NON eredita gli accessi instructor.
Route::prefix('docente')->name('docente.')->middleware(['student.auth', 'professor'])->group(function () {
    Route::get('/', [App\Http\Controllers\Docente\DashboardController::class, 'index'])->name('dashboard');

    // Classi (pacchetto 3)
    Route::get('/classi', [App\Http\Controllers\Docente\ClassController::class, 'index'])->name('classes.index');
    Route::post('/classi', [App\Http\Controllers\Docente\ClassController::class, 'store'])->name('classes.store');
    Route::get('/classi/{class}', [App\Http\Controllers\Docente\ClassController::class, 'show'])->name('classes.show');
    Route::patch('/classi/{class}', [App\Http\Controllers\Docente\ClassController::class, 'update'])->name('classes.update');
    Route::post('/classi/{class}/rigenera-codice', [App\Http\Controllers\Docente\ClassController::class, 'regenerateCode'])->name('classes.regenerate-code');
    Route::patch('/classi/{class}/studenti/{enrollment}', [App\Http\Controllers\Docente\ClassRosterController::class, 'update'])->name('classes.roster.update');

    // Materiali grezzi (pacchetto 4a)
    Route::get('/materiali', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'index'])->name('materials.index');
    Route::get('/materiali/crea', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'create'])->name('materials.create');
    Route::post('/materiali', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'store'])->name('materials.store');
    Route::get('/materiali/{document}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'show'])->name('materials.show');
    Route::patch('/materiali/{document}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'update'])->name('materials.update');
    Route::delete('/materiali/{document}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'destroy'])->name('materials.destroy');
    Route::get('/materiali/{document}/file/{index}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'downloadSource'])->name('materials.download');
    Route::get('/materiali/{document}/stato', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'status'])->name('materials.status');
    Route::post('/materiali/{document}/retry', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'retry'])->name('materials.retry');

    // Generazione e gestione artefatti (pacchetto 5)
    Route::post('/materiali/{document}/genera', [App\Http\Controllers\Docente\ArtifactGenerationController::class, 'store'])->name('artifacts.generate');
    Route::get('/artefatti/{artifact}', [App\Http\Controllers\Docente\ArtifactController::class, 'show'])->name('artifacts.show');
    Route::patch('/artefatti/{artifact}', [App\Http\Controllers\Docente\ArtifactController::class, 'update'])->name('artifacts.update');
    Route::delete('/artefatti/{artifact}', [App\Http\Controllers\Docente\ArtifactController::class, 'destroy'])->name('artifacts.destroy');
    Route::get('/artefatti/{artifact}/stato', [App\Http\Controllers\Docente\ArtifactController::class, 'status'])->name('artifacts.status');
    Route::post('/artefatti/{artifact}/rigenera', [App\Http\Controllers\Docente\ArtifactGenerationController::class, 'regenerate'])->name('artifacts.regenerate');
});

// ===== AREA ADMIN ATHENEUM =====
Route::prefix('admin')->name('admin.')->middleware(['admin.auth'])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('courses/ingest', [App\Http\Controllers\Admin\CourseIngestController::class, 'form'])->name('courses.ingest.form');
    Route::post('courses/ingest/parse', [App\Http\Controllers\Admin\CourseIngestController::class, 'parse'])->name('courses.ingest.parse');
    Route::get('courses/ingest/preview', [App\Http\Controllers\Admin\CourseIngestController::class, 'preview'])->name('courses.ingest.preview');
    Route::post('courses/ingest/confirm', [App\Http\Controllers\Admin\CourseIngestController::class, 'confirm'])->name('courses.ingest.confirm');
    Route::post('courses/ingest/cancel', [App\Http\Controllers\Admin\CourseIngestController::class, 'cancel'])->name('courses.ingest.cancel');
    Route::get('courses/ingest/processing', [App\Http\Controllers\Admin\CourseIngestController::class, 'processing'])->name('courses.ingest.processing');
    Route::get('courses/ingest/status', [App\Http\Controllers\Admin\CourseIngestController::class, 'status'])->name('courses.ingest.status');

    Route::resource('courses', App\Http\Controllers\Admin\CourseController::class);
    Route::resource('courses.modules', App\Http\Controllers\Admin\ModuleController::class);
    Route::resource('courses.modules.materials', App\Http\Controllers\Admin\MaterialController::class);

    // Mappe mentali moduli (Claude API generated, markmap-compatible)
    Route::post('courses/{course}/modules/{module}/mindmap/generate', [App\Http\Controllers\Admin\ModuleMindMapController::class, 'generate'])->name('courses.modules.mindmap.generate');
    Route::patch('courses/{course}/modules/{module}/mindmap', [App\Http\Controllers\Admin\ModuleMindMapController::class, 'update'])->name('courses.modules.mindmap.update');

    // Mappe concettuali (admin) — livello modulo (1 per modulo) + livello corso (1 globale opzionale)
    Route::resource('courses.concept-maps', App\Http\Controllers\Admin\CourseConceptMapController::class);
    Route::post('courses/{course}/concept-maps/{concept_map}/generate', [App\Http\Controllers\Admin\CourseConceptMapController::class, 'generate'])->name('courses.concept-maps.generate');
    Route::post('courses/{course}/concept-maps/auto-create', [App\Http\Controllers\Admin\CourseConceptMapController::class, 'autoCreate'])->name('courses.concept-maps.auto-create');

    Route::prefix('courses/{course}/instructor-materials')
        ->name('courses.instructor-materials.')
        ->group(function () {
            Route::post('/', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'store'])->name('store');
            Route::put('/{material}', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'update'])->name('update');
            Route::post('/{material}/regenerate', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'regenerate'])->name('regenerate');
            Route::delete('/{material}', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'destroy'])->name('destroy');
            Route::get('/{material}/sections', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'manageSections'])->name('sections');
            Route::put('/{material}/sections', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'updateSections'])->name('sections.update');
            Route::post('/{material}/sections/reset', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'resetSections'])->name('sections.reset');
        });

    Route::get('knowledge-base', [App\Http\Controllers\Admin\KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');

    // Soft delete management — DEVONO stare prima del Route::resource('students')
    // perché students/trashed matcha altrimenti students/{student} parametrico.
    Route::get('students/trashed', [App\Http\Controllers\Admin\StudentController::class, 'trashed'])->name('students.trashed');
    Route::patch('students/{id}/restore', [App\Http\Controllers\Admin\StudentController::class, 'restore'])->name('students.restore');
    Route::delete('students/{id}/force-delete', [App\Http\Controllers\Admin\StudentController::class, 'forceDestroy'])->name('students.force-delete');

    Route::resource('students', App\Http\Controllers\Admin\StudentController::class);
    Route::post('students/{student}/courses', [App\Http\Controllers\Admin\StudentController::class, 'assignCourse'])->name('students.assign-course');
    Route::delete('students/{student}/courses/{course}', [App\Http\Controllers\Admin\StudentController::class, 'removeCourse'])->name('students.remove-course');
    Route::patch('students/{student}/courses/{course}/instructor', [App\Http\Controllers\Admin\StudentController::class, 'updateCourseInstructor'])->name('students.update-course-instructor');
    Route::post('students/{student}/send-credentials', [App\Http\Controllers\Admin\StudentController::class, 'sendCredentials'])->name('students.send-credentials');
    Route::patch('students/{student}/system-role', [App\Http\Controllers\Admin\StudentController::class, 'updateSystemRole'])->name('students.update-system-role');

    Route::get('instructors',                                  [App\Http\Controllers\Admin\InstructorController::class, 'index'])->name('instructors.index');
    Route::get('instructors/{instructor}',                     [App\Http\Controllers\Admin\InstructorController::class, 'show'])->name('instructors.show');
    Route::get('instructors/{instructor}/edit',                [App\Http\Controllers\Admin\InstructorController::class, 'edit'])->name('instructors.edit');
    Route::put('instructors/{instructor}',                     [App\Http\Controllers\Admin\InstructorController::class, 'update'])->name('instructors.update');
    Route::post('instructors/{instructor}/courses',            [App\Http\Controllers\Admin\InstructorController::class, 'attachCourse'])->name('instructors.attach-course');
    Route::delete('instructors/{instructor}/courses/{course}', [App\Http\Controllers\Admin\InstructorController::class, 'detachCourse'])->name('instructors.detach-course');
    Route::get('courses/{course}/instructors',                 [App\Http\Controllers\Admin\InstructorController::class, 'forCourse'])->name('courses.instructors');

    Route::resource('quizzes', App\Http\Controllers\Admin\QuizController::class);
    Route::resource('quizzes.questions', App\Http\Controllers\Admin\QuizQuestionController::class);
    Route::get('quizzes/{quiz}/results', [App\Http\Controllers\Admin\QuizController::class, 'results'])->name('quizzes.results');
    Route::post('quizzes/{quiz}/grant-attempt', [App\Http\Controllers\Admin\QuizController::class, 'grantAttempt'])->name('quizzes.grant-attempt');

    Route::get('rag', [App\Http\Controllers\Admin\RagController::class, 'index'])->name('rag.index');
    Route::post('rag/upload', [App\Http\Controllers\Admin\RagController::class, 'upload'])->name('rag.upload');
    Route::delete('rag/{document}', [App\Http\Controllers\Admin\RagController::class, 'destroy'])->name('rag.destroy');

    Route::get('analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics');
    Route::post('analytics/send-reminders', [App\Http\Controllers\Admin\AnalyticsController::class, 'sendReminders'])->name('analytics.send-reminders');
    Route::post('analytics/send-reminder/{student}', [App\Http\Controllers\Admin\AnalyticsController::class, 'sendReminder'])->name('analytics.send-reminder');

    Route::post('upload-image', [App\Http\Controllers\Admin\AdminDashboardController::class, 'uploadImage'])->name('upload-image');
    Route::post('courses/{course}/generate-quiz', [App\Http\Controllers\Admin\CourseController::class, 'generateQuiz'])->name('courses.generate-quiz');

    // Firma digitale certificati — solo legale rappresentante.
    // Le route batch/* sono dichiarate PRIMA di {certificate}/... per
    // evitare che Laravel interpreti "batch" come model binding di Certificate.
    Route::middleware(['legal_representative'])
        ->prefix('certificates/signatures')
        ->name('certificates.signatures.')
        ->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'index'])
                ->name('index');

            Route::get('/batch/download', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'downloadBatch'])
                ->name('batch.download');

            Route::post('/batch/upload', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'uploadBatch'])
                ->name('batch.upload');

            Route::get('/{certificate}/download', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'download'])
                ->name('download');

            Route::post('/{certificate}/upload', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'upload'])
                ->name('upload');
        });

    Route::get('admins',                       [App\Http\Controllers\Admin\AdminAccountController::class, 'index'])->name('admins.index');
    Route::post('admins',                      [App\Http\Controllers\Admin\AdminAccountController::class, 'store'])->name('admins.store');
    Route::patch('admins/{admin}',             [App\Http\Controllers\Admin\AdminAccountController::class, 'update'])->name('admins.update');
    Route::patch('admins/{admin}/password',    [App\Http\Controllers\Admin\AdminAccountController::class, 'password'])->name('admins.password');
    Route::patch('admins/{admin}/toggle',      [App\Http\Controllers\Admin\AdminAccountController::class, 'toggle'])->name('admins.toggle');

    Route::get('settings',                     [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings',                     [App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test-mail',          [App\Http\Controllers\Admin\SettingsController::class, 'testMail'])->name('settings.test-mail');

    Route::get('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'showLogin'])->name('login')->withoutMiddleware(['admin.auth']);
    Route::post('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'login'])->middleware('throttle:login')->name('login.post')->withoutMiddleware(['admin.auth']);
    Route::post('/logout', [App\Http\Controllers\Admin\AdminAuthController::class, 'logout'])->name('logout');

    // 2FA management (admin loggato richiesto, group ereditario)
    Route::prefix('security/2fa')->name('security.2fa.')->group(function () {
        Route::get('/',         [App\Http\Controllers\Admin\TwoFactorController::class, 'show'])->name('show');
        Route::post('/enable',  [App\Http\Controllers\Admin\TwoFactorController::class, 'enable'])->name('enable');
        Route::post('/confirm', [App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->name('confirm');
        Route::post('/disable', [App\Http\Controllers\Admin\TwoFactorController::class, 'disable'])->name('disable');
        Route::post('/recovery-codes', [App\Http\Controllers\Admin\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery.regenerate');
    });
});

// 2FA challenge: l'admin ha password OK ma non e' ancora "logged_in".
// Fuori dal middleware admin.auth (sennò redirect a login infinito).
// Throttle 5/min anti brute-force sul verify.
Route::get('/admin/2fa/challenge', [App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'show'])->name('admin.2fa.challenge');
Route::post('/admin/2fa/verify', [App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('admin.2fa.verify');
