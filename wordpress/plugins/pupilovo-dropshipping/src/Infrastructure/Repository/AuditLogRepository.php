<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH')||exit;

final class AuditLogRepository{
    public function add(?int$supplier_id,?int$job_id,string$severity,string$code,string$message,array$context=[]):void{global$wpdb;$safe=$this->redact($context);$wpdb->insert(Schema::table('logs'),['supplier_id'=>$supplier_id,'job_id'=>$job_id,'severity'=>in_array($severity,['debug','info','warning','error'],true)?$severity:'info','event_code'=>sanitize_key($code),'message'=>sanitize_text_field($message),'context'=>wp_json_encode($safe),'fingerprint'=>hash('sha256',($supplier_id??0).':'.($job_id??0).':'.$code.':'.$message),'created_at'=>current_time('mysql',true)]);}
    public function paginate(int$page=1,int$per_page=50,?int$supplier_id=null,string$severity=''):array{global$wpdb;$page=max(1,$page);$per_page=min(100,max(1,$per_page));$where=['1=1'];$values=[];if($supplier_id){$where[]='supplier_id=%d';$values[]=$supplier_id;}if(in_array($severity,['debug','info','warning','error'],true)){$where[]='severity=%s';$values[]=$severity;}$sql_where=implode(' AND ',$where);$table=Schema::table('logs');$count="SELECT COUNT(*) FROM {$table} WHERE {$sql_where}";$total=(int)$wpdb->get_var($values?$wpdb->prepare($count,...$values):$count);$list="SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d";$rows=$wpdb->get_results($wpdb->prepare($list,...[...$values,$per_page,($page-1)*$per_page]),ARRAY_A)?:[];return['items'=>array_map(static fn($r)=>['id'=>(int)$r['id'],'supplierId'=>$r['supplier_id']?(int)$r['supplier_id']:null,'jobId'=>$r['job_id']?(int)$r['job_id']:null,'severity'=>$r['severity'],'code'=>$r['event_code'],'message'=>$r['message'],'context'=>json_decode((string)$r['context'],true)?:[],'createdAt'=>$r['created_at']],$rows),'page'=>$page,'perPage'=>$per_page,'total'=>$total];}
    private function redact(array$value):array{$secret=['password','secret','token','authorization','api_key','apikey','ciphertext'];$walk=function($input)use(&$walk,$secret){$out=[];foreach($input as$key=>$item){if(in_array(strtolower((string)$key),$secret,true)){$out[$key]='[REDACTED]';}else{$out[$key]=is_array($item)?$walk($item):(is_scalar($item)?sanitize_text_field((string)$item):null);}}return$out;};return$walk($value);}
}
