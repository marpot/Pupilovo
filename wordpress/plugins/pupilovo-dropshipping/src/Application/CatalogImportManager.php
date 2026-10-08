<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Http\SourceHttpClient;
use Pupilovo\SupplierHub\Infrastructure\Repository\AuditLogRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SecretRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SourceRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

defined('ABSPATH')||exit;

final class CatalogImportManager{
    public const HOOK='pupilovo_sh_ingest_catalog';

    public function enqueue(int$supplier_id,int$user_id,?array$upload=null):array{
        global$wpdb;$supplier=(new SupplierRepository())->find($supplier_id);$source=(new SourceRepository())->find_primary($supplier_id);
        if(!$supplier||!$source)throw new\InvalidArgumentException('Najpierw zapisz hurtownię i jej źródło.');
        if(!function_exists('as_enqueue_async_action'))throw new\RuntimeException('Action Scheduler nie jest dostępny.');
        $staged=null;
        if(str_starts_with($source['sourceType'],'file_')){$staged=$this->stage_upload($upload);}
        $context=['sourceId'=>$source['id'],'stagedFile'=>$staged,'requestedAt'=>gmdate('c')];$now=current_time('mysql',true);
        $ok=$wpdb->insert(Schema::table('jobs'),['uuid'=>wp_generate_uuid4(),'supplier_id'=>$supplier_id,'job_type'=>'catalog_ingest','status'=>'queued','dry_run'=>0,'context'=>wp_json_encode($context),'requested_by'=>$user_id,'created_at'=>$now,'heartbeat_at'=>$now]);
        if(!$ok){if($staged)wp_delete_file($staged);throw new\RuntimeException('Nie udało się utworzyć zadania katalogu.');}$job_id=(int)$wpdb->insert_id;
        $action=(int)as_enqueue_async_action(self::HOOK,['job_id'=>$job_id],JobQueue::GROUP,true);if($action<1){if($staged)wp_delete_file($staged);$wpdb->update(Schema::table('jobs'),['status'=>'failed'],['id'=>$job_id]);throw new\RuntimeException('Nie udało się dodać katalogu do kolejki.');}
        return['job'=>(new ImportJobRepository())->get($job_id),'actionId'=>$action];
    }

    public function process(int$job_id):void{
        global$wpdb;$repo=new ImportJobRepository();$job=$repo->get($job_id);if(!$job||$job['type']!=='catalog_ingest'||!in_array($job['status'],['queued','running'],true))return;
        $supplier=(new SupplierRepository())->find((int)$job['supplierId']);$source=(new SourceRepository())->find_primary((int)$job['supplierId']);$path=(string)($job['context']['stagedFile']??'');$downloaded=false;
        $repo->update_job($job_id,['status'=>'running','started_at'=>current_time('mysql',true),'heartbeat_at'=>current_time('mysql',true)]);
        try{
            if(!$supplier||!$source)throw new\RuntimeException('Konfiguracja hurtowni lub źródła nie istnieje.');
            if(str_starts_with($source['sourceType'],'url_')){$headers=$this->auth_headers((new SecretRepository())->get((int)$job['supplierId'],'primary_source'));$download=(new SourceHttpClient())->download((string)$source['location'],$headers,$source['allowInsecureHttp']);if(is_wp_error($download))throw new\RuntimeException($download->get_error_message());$path=$download['path'];$downloaded=true;}
            if(!is_readable($path))throw new\RuntimeException('Plik źródłowy nie jest dostępny.');
            $format=$this->format($source['sourceType']);if(!$format)throw new\RuntimeException('Źródło API wymaga adaptera.');
            $result=(new CatalogIngestService())->ingest(['supplierId'=>(int)$job['supplierId'],'sourceId'=>$source['id'],'file'=>$path,'format'=>$format,'recordPath'=>(string)($source['config']['record_path']??''),'mapping'=>$supplier['fieldMapping'],'parserConfig'=>$source['config'],'configurationHash'=>$source['configurationHash'],'defaultCurrency'=>(string)($source['config']['default_currency']??'PLN'),'maxRecords'=>(int)($source['config']['max_records']??100000),'declaredComplete'=>$source['declaresCompleteFeed']]);
            $context=$job['context'];unset($context['stagedFile']);$context['result']=$result;$repo->update_job($job_id,['status'=>'completed','processed_items'=>$result['seen'],'succeeded_items'=>$result['valid'],'failed_items'=>$result['invalid'],'context'=>wp_json_encode($context),'finished_at'=>current_time('mysql',true),'heartbeat_at'=>current_time('mysql',true)]);$wpdb->update(Schema::table('suppliers'),['last_import_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)],['id'=>$job['supplierId']]);(new AuditLogRepository())->add((int)$job['supplierId'],$job_id,'info','catalog_ingest_completed','Katalog dostawcy został przetworzony.',$result);
        }catch(\Throwable$error){$repo->update_job($job_id,['status'=>'failed','failed_items'=>1,'finished_at'=>current_time('mysql',true),'heartbeat_at'=>current_time('mysql',true)]);(new AuditLogRepository())->add($job['supplierId'],$job_id,'error','catalog_ingest_failed',$error->getMessage());throw$error;}
        finally{if($path!==''&&($downloaded||str_starts_with($path,get_temp_dir()))&&file_exists($path))wp_delete_file($path);}
    }

    private function stage_upload(?array$upload):string{if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new\InvalidArgumentException('Prześlij prawidłowy plik feedu.');$size=filesize($upload['tmp_name']);if($size===false||$size<1||$size>SourceHttpClient::DEFAULT_MAX_BYTES)throw new\InvalidArgumentException('Plik jest pusty albo przekracza 100 MiB.');$target=wp_tempnam('pupilovo-catalog-'.wp_generate_password(12,false));if(!$target||!move_uploaded_file($upload['tmp_name'],$target))throw new\RuntimeException('Nie udało się bezpiecznie przygotować pliku.');chmod($target,0600);return$target;}
    private function format(string$type):?string{foreach(['xml','csv','tsv','json']as$format)if(str_ends_with($type,'_'.$format))return$format;return null;}
    private function auth_headers(?array$secret):array{if(!$secret)return[];return match($secret['type']??'none'){'basic'=>['authorization'=>'Basic '.base64_encode($secret['username'].':'.$secret['password'])],'bearer'=>['authorization'=>'Bearer '.$secret['token']],'headers'=>$secret['headers']??[],default=>[]};}
}
