<?php

namespace App\Enums;

enum NotificationType: string
{
    case TASK = 'task';
    case DOCUMENT = 'document';
    case DAILY_LOG = 'daily_log';
    case MACHINE_ASSIGNMENT = 'machine_assignment';
    case ATTENDANCE = 'attendance';
    case NOTE = 'note';


    case EXPENSE = 'expense';
    case DELIVERY_NOTE = 'delivery_note';
    case MACHINE = 'machine';
}
