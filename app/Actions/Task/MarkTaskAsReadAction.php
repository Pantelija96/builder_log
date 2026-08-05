<?php

namespace App\Actions\Task;

use App\Actions\BaseAction;
use App\Models\Task;

class MarkTaskAsReadAction extends BaseAction
{
    public function execute(Task $task,): Task {
        $task->markAsRead();
        return $task->refresh();
    }
}
