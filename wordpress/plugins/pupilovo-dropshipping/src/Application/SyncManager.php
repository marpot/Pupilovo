<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;

defined('ABSPATH') || exit;

final class SyncManager {
    public function __construct(private readonly ImportJobRepository $jobs = new ImportJobRepository()) {}

    /** @return array<string,mixed> */
    public function enqueue(int $supplier_id, int $user_id, array $managed_fields = ['price','stock']): array {
        global $wpdb;
        if ($supplier_id < 1) { throw new \InvalidArgumentException('Wybierz hurtownię.'); }
        $active = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM '.Schema::table('jobs')." WHERE supplier_id=%d AND job_type='sync' AND status IN ('planning','awaiting_approval','queued','running')",
            $supplier_id
        ));
        if ($active > 0) { throw new \RuntimeException('Synchronizacja tej hurtowni już trwa lub oczekuje.'); }
        $allowed = ['name','description','short_description','price','stock','categories','attributes','images','weight','dimensions'];
        $managed_fields = array_values(array_intersect($allowed, array_map('sanitize_key', $managed_fields)));
        if ($managed_fields === []) { throw new \InvalidArgumentException('Wybierz co najmniej jedno pole synchronizacji.'); }
        $plan = (new ImportPreviewService())->create($supplier_id, $user_id, ['linkedOnly'=>true,'managedFields'=>$managed_fields]);
        $job_id = (int) $plan['job']['id'];
        $this->jobs->update_job($job_id, ['job_type'=>'sync']);
        $action_id = (new JobQueue())->enqueue_import($job_id, $user_id);
        return ['job'=>$this->jobs->get($job_id),'items'=>$this->jobs->items($job_id),'actionId'=>$action_id];
    }
}
