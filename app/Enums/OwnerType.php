<?php

namespace App\Enums;

enum OwnerType: string
{
    case COMPANY = 'company';
    case SUPPLIER = 'supplier';
    case SUBCONTRACTOR = 'subcontractor';
    case WORKER = 'worker';
}
