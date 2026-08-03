<?php

namespace App\Enums;

enum WorkerRole: string
{
    case ADMIN = 'admin';
    case SITE_MANAGER = 'site_manager';
    case OPERATOR = 'operator';
    case WORKER = 'worker';
}
