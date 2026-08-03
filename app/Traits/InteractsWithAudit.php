<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait InteractsWithAudit
{
    protected function original(Model $model): array
    {
        return $model->getOriginal();
    }

    protected function current(Model $model): array
    {
        $model->refresh();

        return $model->getAttributes();
    }

    protected function changed(array $old, array $new): array
    {
        return array_diff_assoc($new, $old);
    }

    protected function oldChanged(array $old, array $new): array
    {
        return array_intersect_key(
            $old,
            array_diff_assoc($new, $old),
        );
    }
}
