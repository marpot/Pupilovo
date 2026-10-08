<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\AuditLogRepository;

defined('ABSPATH') || exit;

final class JobQueue {
    public const IMPORT_HOOK = 'pupilovo_sh_process_import_batch';
    public const GROUP = 'pupilovo-supplier-hub';

    public function __construct(private readonly ImportJobRepository $jobs = new ImportJobRepository()) {}

    public function enqueue_import(int $job_id, int $user_id): int {
        $job = $this->jobs->get($job_id);
        if (!$job || $job['status'] !== 'awaiting_approval') { throw new \RuntimeException('Plan nie oczekuje na zatwierdzenie.'); }
        if (!function_exists('as_enqueue_async_action')) { throw new \RuntimeException('Action Scheduler nie jest dostępny.'); }
        $action_id = (int) as_enqueue_async_action(self::IMPORT_HOOK, ['job_id'=>$job_id,'user_id'=>$user_id], self::GROUP, true);
        if ($action_id < 1) { throw new \RuntimeException('Nie udało się dodać importu do kolejki.'); }
        $this->jobs->update_job($job_id, ['status'=>'queued','dry_run'=>0,'requested_by'=>$user_id,'heartbeat_at'=>current_time('mysql',true)]);
        return $action_id;
    }

    public function process_import_batch(int $job_id, int $user_id): void {
        $result = (new ImportExecutor())->process_batch($job_id, $user_id, 10);
        (new AuditLogRepository())->add($result['job']['supplierId'],$job_id,'info','job_batch_processed','Przetworzono partię zadania.',['processed'=>$result['processedInBatch'],'hasMore'=>$result['hasMore']]);
        if ($result['hasMore']) { as_schedule_single_action(time()+max(2,$result['nextDelay']), self::IMPORT_HOOK, ['job_id'=>$job_id,'user_id'=>$user_id], self::GROUP, true); }
    }

    public function cancel(int $job_id): int {
        if (!function_exists('as_get_scheduled_actions')) { return 0; }
        $count = 0;
        $actions = as_get_scheduled_actions(['hook'=>self::IMPORT_HOOK,'group'=>self::GROUP,'status'=>\ActionScheduler_Store::STATUS_PENDING,'per_page'=>100], 'OBJECT');
        foreach ($actions as $action) {
            $args = $action->get_args();
            if ((int)($args['job_id']??0) !== $job_id) { continue; }
            as_unschedule_action(self::IMPORT_HOOK, $args, self::GROUP); ++$count;
        }
        $job = $this->jobs->get($job_id);
        if ($job && in_array($job['status'], ['queued','running'], true)) { $this->jobs->update_job($job_id, ['status'=>'cancelled','finished_at'=>current_time('mysql',true)]); }
        return $count;
    }
}
