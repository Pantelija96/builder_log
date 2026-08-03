<?php

namespace App\Actions;

use Closure;
use Illuminate\Support\Facades\DB;

abstract class BaseAction
{
    protected function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
