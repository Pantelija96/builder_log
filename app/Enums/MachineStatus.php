<?php

namespace App\Enums;

enum MachineStatus: string
{
    case ACTIVE = 'active';
    case SERVICE = 'service';
    case BROKEN = 'broken';
    case INACTIVE = 'inactive';
}
