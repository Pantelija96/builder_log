<?php

namespace App\Enums;

enum NotificationType:string
{
    case TASK='task';

    case EXPENSE='expense';

    case DELIVERY_NOTE='delivery_note';

    case DAILY_LOG='daily_log';

    case MACHINE='machine';
}
