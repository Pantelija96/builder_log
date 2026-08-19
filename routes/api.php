<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\ConstructionSiteController;
//use App\Http\Controllers\Api\MachineGetController;
use \App\Http\Controllers\MachineController;
use App\Http\Controllers\Api\SubcontractorController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\CashAdvanceController;
use App\Http\Controllers\ConstructionSiteAssignmentController;
use App\Http\Controllers\DailyLogController;
use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExcavatorLogController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialSummaryController;
use App\Http\Controllers\MachineAssignmentController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SiteManagerController;
use App\Http\Controllers\SubcontractorLogController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TruckLogController;
use App\Http\Controllers\WorkerAttendanceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

Route::get('/ping', fn () => response()->json([
    'message' => 'API works!',
]));

Route::get('/test', function (){
    dd(
        config('app.timezone'),
        now()->toDateTimeString(),
        now()->timezoneName,
    );
});

Route::get('/system/reset/{token}', function (string $token) {

    abort_unless(
        hash_equals((string) env('RESET_TOKEN'), $token),
        403,
        'Unauthorized.'
    );

    try {

        Artisan::call('migrate:fresh', [
            '--seed'  => true,
            '--force' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Database successfully reset and seeded.',
            'command' => 'php artisan migrate:fresh --seed',
            'output'  => Artisan::output(),
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'success'   => false,
            'message'   => $e->getMessage(),
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'output'    => Artisan::output(),
        ], 500);

    }

});

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/workers', [WorkerController::class, 'index']);
        Route::get('/companies', [CompanyController::class, 'index']);
        Route::get('/construction-sites', [ConstructionSiteController::class, 'index']);
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/subcontractors', [SubcontractorController::class, 'index']);
        Route::get('/expenses', [ExpenseController::class, 'getAll']);

        Route::controller(DailyLogController::class)
            ->prefix('daily-logs')
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/{dailyLog}', 'show');
                Route::post('/', 'store');
                Route::post('/{dailyLog}/lock', 'lock');
                Route::post('/{dailyLog}/unlock', 'unlock');
            });

        Route::controller(WorkerAttendanceController::class)
            ->prefix('daily-logs/{dailyLog}/worker-attendances')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{workerAttendance}', 'update');
                Route::delete('/{workerAttendance}', 'destroy');
            });

        Route::controller(ExpenseController::class)
            ->prefix('daily-logs/{dailyLog}/expenses')
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/history', 'history');
                Route::post('/', 'store');
                Route::patch('/{expense}', 'update');
                Route::delete('/{expense}', 'destroy');
            });

        Route::controller(ExpenseController::class)
            ->prefix('expenses')
            ->group(function () {
                Route::get('/', 'getAll');
            });

        Route::controller(AttachmentController::class)->group(function () {
            Route::post('/daily-logs/{dailyLog}/attachments', 'uploadToDailyLog');
            Route::post('/expenses/{expense}/attachments', 'uploadToExpense');
            Route::get('/attachments/{attachment}', 'download')->name('attachments.download');
            Route::delete('/attachments/{attachment}', 'destroy');
        });

        Route::controller(ConstructionSiteAssignmentController::class)
            ->prefix('construction-sites/{constructionSite}/site-managers')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/{worker}', 'assign');
                Route::delete('/{worker}', 'remove');
            });

        Route::controller(DeliveryNoteController::class)
            ->prefix('delivery-notes')
            ->group(function () {
                Route::get('/', 'getAll');
            });

        Route::controller(DeliveryNoteController::class)
            ->prefix('daily-logs/{dailyLog}/delivery-notes')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{deliveryNote}', 'update');
                Route::delete('/{deliveryNote}', 'destroy');
            });

        Route::controller(SubcontractorLogController::class)
            ->prefix('daily-logs/{dailyLog}/subcontractors')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{subcontractorLog}', 'update');
                Route::delete('/{subcontractorLog}', 'destroy');
            });

        Route::controller(NoteController::class)
            ->prefix('daily-logs/{dailyLog}/notes')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{note}', 'update');
                Route::delete('/{note}', 'destroy');
            });

        Route::controller(NoteController::class)
            ->prefix('notes')
            ->group(function () {
                Route::get('/', 'getAll');
            });

        Route::controller(NotificationController::class)
            ->prefix('notifications')
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/unread-count', 'unreadCount');
                Route::patch('/{notification}/read', 'read');
            });

        Route::controller(TaskController::class)
            ->prefix('tasks')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{task}', 'update');
                Route::delete('/{task}', 'destroy');
            });

        Route::controller(TaskController::class)
            ->prefix('my/tasks')
            ->group(function () {
                Route::get('/', 'myTasks');
                Route::patch('/{task}/read', 'read');
                Route::patch('/{task}/complete', 'complete');
                Route::patch('/{task}/reopen', 'reopen');
            });

        Route::controller(CashAdvanceController::class)
            ->prefix('cash-advances')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::patch('/{cashAdvance}', 'update');
                Route::delete('/{cashAdvance}', 'destroy');

            });


        Route::controller(FinancialSummaryController::class)
            ->prefix('financial-summary')
            ->group(function () {
                Route::get('/', 'index');
            });

        Route::prefix('documents')
            ->controller(DocumentController::class)
            ->group(function () {
                Route::get('/', 'getAll',);
                Route::post('/', 'store',);
                Route::patch('/{document}', 'update',);
                Route::delete('/{document}', 'destroy',);
                Route::get('/{document}/download', 'download',)->name('documents.download');
            });

        Route::controller(MachineAssignmentController::class)
            ->prefix('machine-assignments')
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/current-excavator', 'current');
            });

        Route::controller(MachineController::class)
            ->prefix('machines')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('/{machine}', 'show');
                Route::patch('/{machine}', 'update');
                Route::delete('/{machine}', 'destroy');
            });

        Route::controller(TruckLogController::class)
            ->prefix('truck-logs')
            ->group(function () {
                Route::get('/', 'index');
                Route::get('/available', 'available');
                Route::get('/{truckLog}', 'show');
                Route::post('/', 'store');
                Route::patch('/{truckLog}', 'update');
                Route::delete('/{truckLog}', 'destroy');
            });

        Route::controller(ExcavatorLogController::class)
            ->prefix('excavator-logs')
            ->group(function () {
                Route::get('/available', 'available');
                Route::get('/occupied', 'occupied');

                Route::post('/daily-logs/{dailyLog}', 'store');
                Route::post('/operator', 'storeForOperator');

                Route::patch('/{excavatorLog}', 'update');
                Route::delete('/{excavatorLog}', 'destroy');
            });

        Route::controller(ConstructionSiteController::class)
            ->prefix('construction-sites')
            ->group(function () {
                Route::get('/{constructionSite}/financial-summary', 'financialSummary');
            });

        Route::controller(SiteManagerController::class)
            ->prefix('site-managers')
            ->group(function () {
                Route::get('/{siteManager}/overall', 'overall');
                Route::get('/{siteManager}/assigned-sites', 'assignedSites');
            });

        Route::controller(WorkerController::class)
            ->prefix('workers')
            ->group(function () {
                Route::get('/{worker}', 'show');
                Route::post('/', 'store');
                Route::patch('/{worker}', 'update');
                Route::delete('/{worker}', 'destroy');
                Route::get('/{worker}/work-history', 'workHistory');
            });


    });
});
