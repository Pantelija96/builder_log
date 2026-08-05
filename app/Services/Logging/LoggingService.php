<?php

namespace App\Services\Logging;

use App\Enums\LogEvent;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class LoggingService
{
    public function activity(?Worker $actor, Model $subject, LogEvent $event,): void {
        $this->storeActivity(
            actor: $actor,
            subject: $subject,
            event: $event,
            context: $this->context($subject),
        );
    }

    public function audit(?Worker $actor, Model $subject, LogEvent $event, ?array $oldValues = null, ?array $newValues = null, ?string $reason = null,): void {
        $newValues ??= $subject->getAttributes();
        $this->storeAudit(
            actor: $actor,
            subject: $subject,
            event: $event,
            context: $this->context($subject),
            oldValues: $oldValues,
            newValues: $newValues,
            reason: $reason,
        );
    }

    private function storeActivity(?Worker $actor, Model $subject, LogEvent $event, array $context,): void {
        $data = [
            'company_id' => $context['company_id'],
            'daily_log_id' => $context['daily_log_id'],
            'construction_site_id' => $context['construction_site_id'],
            'actor_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'description' => __('activity.' . $event->value),
            'date' => ($context['date'] ?? now())->format('Y-m-d'),
        ];
        ActivityLog::create($data);
    }

    private function storeAudit(?Worker $actor, Model $subject, LogEvent $event, array $context, array $oldValues = null, array $newValues = null, ?string $reason = null,): void {
        AuditLog::create([
            'company_id' => $context['company_id'],
            'actor_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'old_values' => empty($oldValues) ? null : $oldValues,
            'new_values' => empty($newValues) ? null : $newValues,
            'reason' => $reason,
        ]);
    }

    private function ensureLoggable(Model $subject,): void {
        if (! method_exists($subject, 'logContext')) {
            throw new LogicException(sprintf(
                'Model [%s] must use the Loggable trait.',
                $subject::class,
            ));
        }
    }

    private function context(Model $subject): array
    {
        $this->ensureLoggable($subject);
        return $subject->logContext();
    }
}
