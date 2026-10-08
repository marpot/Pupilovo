<?php
/** Action Scheduler queue integration checks without product fixtures. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Application\JobQueue;
use Pupilovo\SupplierHub\Application\SyncScheduler;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

$checks=0;$user_id=0;$supplier_id=0;$job_ids=[];
function e_check($condition,string $label):void{if(!$condition){throw new RuntimeException('FAIL: '.$label);}++$GLOBALS['checks'];echo 'PASS: '.$label.PHP_EOL;}
try{
    global $wpdb;$run='psh-e-'.bin2hex(random_bytes(5));
    $user_id=wp_insert_user(['user_login'=>$run,'user_email'=>$run.'@example.test','user_pass'=>wp_generate_password(24),'role'=>'administrator']);if(is_wp_error($user_id))throw new RuntimeException($user_id->get_error_message());wp_set_current_user($user_id);
    e_check(function_exists('as_enqueue_async_action'),'Action Scheduler API is available');
    $repo=new ImportJobRepository();$context=['pricingRuleHashes'=>[],'catalogSnapshotHash'=>hash('sha256','empty')];
    $job_ids[]=$first=$repo->create_plan(null,$context,$user_id);$repo->finish_plan($first,['create'=>0,'update'=>0,'conflict'=>0,'skip'=>0]);
    $action=(new JobQueue())->enqueue_import($first,$user_id);
    e_check($action>0&&$repo->get($first)['status']==='queued','approved import is persisted as queued Action Scheduler work');
    $cancelled=(new JobQueue())->cancel($first);
    e_check($cancelled===1&&$repo->get($first)['status']==='cancelled','pending job can be cancelled without deleting its audit record');

    $job_ids[]=$second=$repo->create_plan(null,$context,$user_id);$repo->finish_plan($second,['create'=>0,'update'=>0,'conflict'=>0,'skip'=>0]);
    (new JobQueue())->enqueue_import($second,$user_id);(new JobQueue())->process_import_batch($second,$user_id);
    e_check($repo->get($second)['status']==='completed','queue worker completes an empty resumable batch deterministically');
    (new JobQueue())->cancel($second);
    $supplier=(new SupplierRepository())->create(['name'=>$run,'sourceType'=>'file_json','adapterKey'=>'','status'=>'active','sourceConfig'=>[],'fieldMapping'=>[]],$user_id);$supplier_id=(int)$supplier['id'];
    $scheduled=(new SyncScheduler())->configure($supplier_id,true,900,['price','stock'],$user_id);
    e_check($scheduled['actionId']>0&&(new SupplierRepository())->find($supplier_id)['syncEnabled'],'recurring supplier synchronization persists configuration and action');
    $disabled=(new SyncScheduler())->configure($supplier_id,false,900,['price','stock'],$user_id);
    e_check(!$disabled['enabled']&&!(new SupplierRepository())->find($supplier_id)['syncEnabled'],'disabling schedule removes recurring work and persists state');
    echo $checks.' Stage E queue checks passed.'.PHP_EOL;
}finally{
    global $wpdb;foreach($job_ids as $id){(new JobQueue())->cancel((int)$id);$wpdb->delete(Schema::table('job_items'),['job_id'=>(int)$id],['%d']);$wpdb->delete(Schema::table('jobs'),['id'=>(int)$id],['%d']);}
    if($supplier_id>0){(new SyncScheduler())->unschedule($supplier_id);$wpdb->delete(Schema::table('suppliers'),['id'=>$supplier_id],['%d']);}
    if($user_id>0)wp_delete_user($user_id);wp_set_current_user(0);echo 'Stage E queue fixtures cleaned up.'.PHP_EOL;
}
