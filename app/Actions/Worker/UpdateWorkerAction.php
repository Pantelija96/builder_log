<?php

namespace App\Actions\Worker;

use App\Actions\BaseAction;
use App\DTO\Worker\UpdateWorkerData;
use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class UpdateWorkerAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(Worker $worker, UpdateWorkerData $data, Worker $currentWorker, ?string $reason = null,): Worker {
        return $this->transaction(function () use ($worker, $data, $currentWorker, $reason,) {
            $oldValues = $worker->getAttributes();
            $values = [];

            if (in_array('first_name', $data->providedFields, true)) {
                $values['first_name'] = $data->firstName;
            }

            if (in_array('last_name', $data->providedFields, true)) {
                $values['last_name'] = $data->lastName;
            }

            if (in_array('phone', $data->providedFields, true)) {
                $values['phone'] = $data->phone;
            }

            if (in_array('role', $data->providedFields, true)) {
                $values['role'] = $data->role;
            }

            if (in_array('hourly_rate', $data->providedFields, true)) {
                $values['hourly_rate'] = $data->hourlyRate;
            }

            if (in_array('username', $data->providedFields, true)) {
                $values['username'] = $data->username;
            }

            if (in_array('password', $data->providedFields, true)) {
                $values['password'] = $data->password;
            }

            if (in_array('email', $data->providedFields, true)) {
                $values['email'] = $data->email;
            }

            if (in_array('is_active', $data->providedFields, true)) {
                $values['is_active'] = $data->isActive;
            }

            /*
             * Ako frontend šalje construction_site_ids,
             * proveravamo da li je dozvoljeno menjati assignment-e.
             */
            if (in_array('construction_site_ids', $data->providedFields, true,)) {
                $this->ensureConstructionSitesCanBeUpdated(
                    worker: $worker,
                    data: $data,
                    currentWorker: $currentWorker,
                );
            }

            /*
             * Ako site manager menja role u neku drugu,
             * moramo proveriti njegove postojeće assignment-e.
             */
            if (in_array('role', $data->providedFields, true) && $worker->isSiteManager() && $data->role !== WorkerRole::SITE_MANAGER) {
                $this->ensureSiteManagerRoleCanBeRemoved(
                    worker: $worker,
                );
            }

            $worker->update($values);

            /*
             * construction_site_ids nije običan atribut Worker modela.
             * Menjamo many-to-many pivot samo kada je field
             * eksplicitno poslat.
             */
            if (in_array('construction_site_ids', $data->providedFields, true,)) {
                $worker
                    ->constructionSites()
                    ->sync($data->constructionSiteIds);
            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $worker,
                event: LogEvent::WORKER_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $worker,
                event: LogEvent::WORKER_UPDATED,
                oldValues: $oldValues,
                newValues: $worker->fresh()->getAttributes(),
                reason: $reason,
            );

            return $worker->fresh([
                'company',
                'constructionSites',
            ]);
        });
    }

    private function ensureConstructionSitesCanBeUpdated(Worker $worker, UpdateWorkerData $data, Worker $currentWorker,): void {
        /*
         * Role koji će worker imati nakon PATCH-a.
         *
         * Ako role nije poslat, koristimo trenutni role.
         */
        $resultingRole = $data->role ?? $worker->role;

        if ($resultingRole !== WorkerRole::SITE_MANAGER) {
            if (! empty($data->constructionSiteIds)) {
                throw new BusinessException(
                    'Construction sites can only be assigned to site managers.'
                );
            }
        }

        /*
         * Sva poslata gradilišta moraju pripadati istoj kompaniji.
         */
        if (! empty($data->constructionSiteIds)) {
            $validCount = ConstructionSite::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->whereIn(
                    'id',
                    $data->constructionSiteIds,
                )
                ->count();

            if ($validCount !== count($data->constructionSiteIds)) {
                throw new BusinessException(
                    'All construction sites must belong to the same company.'
                );
            }
        }

        /*
         * Trenutno dodeljena gradilišta.
         */
        $currentIds = $worker
            ->constructionSites()
            ->pluck('construction_sites.id')
            ->sort()
            ->values()
            ->all();

        /*
         * Nova lista koju je frontend poslao.
         */
        $newIds = collect($data->constructionSiteIds)
            ->sort()
            ->values()
            ->all();

        /*
         * Nas zanimaju gradilišta sa kojih se manager uklanja.
         */
        $removedIds = array_values(
            array_diff(
                $currentIds,
                $newIds,
            )
        );

        if (empty($removedIds)) {
            return;
        }

        /*
         * Čuvamo postojeće poslovno pravilo:
         * manager ne može biti uklonjen sa gradilišta
         * koje već ima Daily Log za danas.
         */
        $hasDailyLogToday = DailyLog::query()
            ->whereIn('construction_site_id', $removedIds,)
            ->whereDate('date', today())
            ->exists();

        if ($hasDailyLogToday) {
            throw new BusinessException(
                'Site manager cannot be removed from a construction site while a daily log exists for today.'
            );
        }
    }

    private function ensureSiteManagerRoleCanBeRemoved(Worker $worker,): void {
        $constructionSiteIds = $worker
            ->constructionSites()
            ->pluck('construction_sites.id')
            ->all();

        /*
         * Nema assignment-a, role može normalno da se promeni.
         */
        if (empty($constructionSiteIds)) {
            return;
        }

        /*
         * Ako postoji Daily Log danas na bilo kom njegovom
         * gradilištu, ne dozvoljavamo promenu role.
         */
        $hasDailyLogToday = DailyLog::query()
            ->whereIn('construction_site_id', $constructionSiteIds,)
            ->whereDate('date', today())
            ->exists();

        if ($hasDailyLogToday) {
            throw new BusinessException(
                'Site manager role cannot be changed while a daily log exists for an assigned construction site today.'
            );
        }

        /*
         * Worker više neće biti site manager,
         * pa uklanjamo njegove assignment-e.
         */
        $worker->constructionSites()->detach();
    }
}
