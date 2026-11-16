<?php

namespace App\Actions\Supplier;

use App\Actions\BaseAction;
use App\Models\Supplier;
use App\Models\Worker;

class DeleteSupplierAction extends BaseAction
{
    public function execute(
        Supplier $supplier,
        Worker $currentWorker,
        string $reason,
    ): void {
        $this->transaction(function () use (
            $supplier,
        ) {
            $supplier->delete();
        });
    }
}
