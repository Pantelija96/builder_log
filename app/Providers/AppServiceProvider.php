<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\CashAdvance;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Document;
use App\Models\ExcavatorLog;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Note;
use App\Models\Subcontractor;
use App\Models\SubcontractorLog;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'attachment' => Attachment::class,
            'cash_advance' => CashAdvance::class,
            'company' => Company::class,
            'construction_site' => ConstructionSite::class,
            'daily_log' => DailyLog::class,
            'delivery_note' => DeliveryNote::class,
            'document' => Document::class,
            'excavator_log' => ExcavatorLog::class,
            'expense' => Expense::class,
            'machine' => Machine::class,
            'machine_assignment' => MachineAssignment::class,
            'note' => Note::class,
            'subcontractor' => Subcontractor::class,
            'subcontractor_log' => SubcontractorLog::class,
            'supplier' => Supplier::class,
            'task' => Task::class,
            'truck_log' => TruckLog::class,
            'worker' => Worker::class,
            'worker_attendance' => WorkerAttendance::class,
        ]);

        $this->configureApiDocumentation();
    }

    private function configureApiDocumentation(): void
    {
        // The order here is also the order of the OpenAPI tags.
        $groups = [
            'Authentication' => ['AuthController'],
            'Companies' => ['CompanyController'],
            'Workers' => ['WorkerController'],
            'Construction Sites' => ['ConstructionSiteController'],
            'Construction Site Assignments' => ['ConstructionSiteAssignmentController', 'SiteManagerController'],
            'Daily Logs' => ['DailyLogController'],
            'Worker Attendance' => ['WorkerAttendanceController'],
            'Machines' => ['MachineController'],
            'Machine Assignments' => ['MachineAssignmentController'],
            'Excavators' => ['ExcavatorLogController'],
            'Trucks' => ['TruckLogController'],
            'Expenses' => ['ExpenseController'],
            'Financial Reports' => ['ConstructionFinancialSummaryController', 'FinancialSummaryController'],
            'Worker Salaries' => ['WorkerSalaryController'],
            'Worker Payments' => ['WorkerPaymentController'],
            'Cash Advances' => ['CashAdvanceController'],
            'Suppliers' => ['SupplierController'],
            'Subcontractors' => ['SubcontractorController'],
            'Subcontractor Logs' => ['SubcontractorLogController'],
            'Documents' => ['DocumentController'],
            'Attachments' => ['AttachmentController'],
            'Delivery Notes' => ['DeliveryNoteController'],
            'Notes' => ['NoteController'],
            'Tasks' => ['TaskController'],
            'Notifications' => ['NotificationController'],
            'Push Notifications' => ['PushNotificationController'],
        ];

        $controllerTags = [];
        foreach ($groups as $tag => $controllers) {
            foreach ($controllers as $controller) {
                $controllerTags[$controller] = $tag;
            }
        }

        Scramble::resolveTagsUsing(static function (RouteInfo $routeInfo) use ($controllerTags): array {
            $controller = class_basename($routeInfo->className() ?? '');

            return [$controllerTags[$controller] ?? Str::headline(Str::beforeLast($controller, 'Controller'))];
        });

        Scramble::configure()->withTags(array_map(
            static fn (string $name): Tag => new Tag($name),
            array_keys($groups),
        ));
    }
}
