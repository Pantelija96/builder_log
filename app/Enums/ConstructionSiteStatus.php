<?php

namespace App\Enums;

enum ConstructionSiteStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';
}
