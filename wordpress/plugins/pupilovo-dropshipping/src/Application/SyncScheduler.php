<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class SyncScheduler {
    public const HOOK = 'pupilovo_sh_scheduled_supplier_sync';

    /** @return array<string,mixed> */
    public function configure(int $supplier_id, bool $enabled, int $interval, array $managed_fields, int $user_id): array {
        global $wpdb;
        $interval = min(2592000, max(900, $interval));
        if (!(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('suppliers').' WHERE id=%d AND status<>\'deleted\'',$supplier_id))) { throw new \InvalidArgumentException('Nie znaleziono hurtowni.'); }
        $this->unschedule($supplier_id);
        $config=['intervalSeconds'=>$interval,'managedFields'=>array_values(array_map('sanitize_key',$managed_fields)),'updatedAt'=>gmdate('c')];
        $wpdb->update(Schema::table('suppliers'),['sync_enabled'=>$enabled?1:0,'schedule_config'=>wp_json_encode($config),'updated_at'=>current_time('mysql',true)],['id'=>$supplier_id],null,['%d']);
        $action_id=0;
        if($enabled){if(!function_exists('as_schedule_recurring_action'))throw new \RuntimeException('Action Scheduler nie jest dostępny.');$action_id=(int)as_schedule_recurring_action(time()+$interval,$interval,self::HOOK,['supplier_id'=>$supplier_id,'user_id'=>$user_id,'managed_fields'=>$config['managedFields']],JobQueue::GROUP,true);if($action_id<1)throw new \RuntimeException('Nie udało się zaplanować synchronizacji.');}
        return ['enabled'=>$enabled,'actionId'=>$action_id,'config'=>$config];
    }

    public function run(int $supplier_id,int $user_id,array $managed_fields):void{try{(new SyncManager())->enqueue($supplier_id,$user_id,$managed_fields);}catch(\Throwable $error){$this->log($supplier_id,'scheduled_sync_skipped',$error->getMessage());}}

    public function unschedule(int $supplier_id):int{
        if(!function_exists('as_get_scheduled_actions'))return 0;$count=0;
        $actions=as_get_scheduled_actions(['hook'=>self::HOOK,'group'=>JobQueue::GROUP,'status'=>\ActionScheduler_Store::STATUS_PENDING,'per_page'=>100],'OBJECT');
        foreach($actions as $action){$args=$action->get_args();if((int)($args['supplier_id']??0)!==$supplier_id)continue;as_unschedule_action(self::HOOK,$args,JobQueue::GROUP);++$count;}return$count;
    }

    private function log(int $supplier_id,string $code,string $message):void{global$wpdb;$wpdb->insert(Schema::table('logs'),['supplier_id'=>$supplier_id,'severity'=>'warning','event_code'=>$code,'message'=>sanitize_text_field($message),'context'=>wp_json_encode([]),'fingerprint'=>hash('sha256',$supplier_id.':'.$code.':'.$message),'created_at'=>current_time('mysql',true)]);}
}
