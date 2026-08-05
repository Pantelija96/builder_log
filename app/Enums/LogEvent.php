<?php

namespace App\Enums;

enum LogEvent: string
{
    /*
    |--------------------------------------------------------------------------
    | Daily Logs
    |--------------------------------------------------------------------------
    */

    case DAILY_LOG_CREATED = 'daily_log.created';
    case DAILY_LOG_UPDATED = 'daily_log.updated';
    case DAILY_LOG_LOCKED = 'daily_log.locked';
    case DAILY_LOG_UNLOCKED = 'daily_log.unlocked';
    case DAILY_LOG_DELETED = 'daily_log.deleted';

    /*
    |--------------------------------------------------------------------------
    | Worker Attendance
    |--------------------------------------------------------------------------
    */

    case WORKER_ATTENDANCE_CREATED = 'worker_attendance.created';
    case WORKER_ATTENDANCE_UPDATED = 'worker_attendance.updated';
    case WORKER_ATTENDANCE_DELETED = 'worker_attendance.deleted';

    /*
    |--------------------------------------------------------------------------
    | Expenses
    |--------------------------------------------------------------------------
    */

    case EXPENSE_CREATED = 'expense.created';
    case EXPENSE_UPDATED = 'expense.updated';
    case EXPENSE_DELETED = 'expense.deleted';

    /*
    |--------------------------------------------------------------------------
    | Materials
    |--------------------------------------------------------------------------
    */

    case MATERIAL_CREATED = 'material.created';
    case MATERIAL_UPDATED = 'material.updated';
    case MATERIAL_DELETED = 'material.deleted';

    /*
    |--------------------------------------------------------------------------
    | Machines
    |--------------------------------------------------------------------------
    */

    case MACHINE_CREATED = 'machine.created';
    case MACHINE_UPDATED = 'machine.updated';
    case MACHINE_DELETED = 'machine.deleted';

    /*
    |--------------------------------------------------------------------------
    | Delivery Notes
    |--------------------------------------------------------------------------
    */

    case DELIVERY_NOTE_CREATED = 'delivery_note.created';
    case DELIVERY_NOTE_UPDATED = 'delivery_note.updated';
    case DELIVERY_NOTE_DELETED = 'delivery_note.deleted';

    /*
    |--------------------------------------------------------------------------
    | Notes
    |--------------------------------------------------------------------------
    */

    case NOTE_CREATED = 'note.created';
    case NOTE_UPDATED = 'note.updated';
    case NOTE_DELETED = 'note.deleted';

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */

    case ATTACHMENT_UPLOADED = 'attachment.uploaded';
    case ATTACHMENT_DELETED = 'attachment.deleted';


    /*
    |--------------------------------------------------------------------------
    | Subcontractor Log
    |--------------------------------------------------------------------------
    */
    case SUBCONTRACTOR_LOG_UPDATED = 'subcontractor.log.updated';
    case SUBCONTRACTOR_LOG_CREATED = 'subcontractor.log.created';
    case SUBCONTRACTOR_LOG_DELETED = 'subcontractor.log.deleted';

    /*
    |--------------------------------------------------------------------------
    | Tasks
    |--------------------------------------------------------------------------
    */

    case TASK_CREATED = 'task.created';
    case TASK_UPDATED = 'task.updated';
    case TASK_DELETED = 'task.deleted';
    case TASK_COMPLETED = 'task.completed';
    case TASK_REOPENED = 'task.reopened';
}
