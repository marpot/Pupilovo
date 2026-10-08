<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\AuditLogRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH')||exit;

final class DiagnosticsRestController{
    private const NS='pupilovo-supplier-hub/v1';
    public static function register_routes():void{
        register_rest_route(self::NS,'/logs',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'logs'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
        register_rest_route(self::NS,'/jobs',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'jobs'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
        register_rest_route(self::NS,'/diagnostics',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'diagnostics'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
    }
    public static function logs(WP_REST_Request$request):WP_REST_Response{return new WP_REST_Response((new AuditLogRepository())->paginate(max(1,(int)$request->get_param('page')),min(100,max(1,(int)($request->get_param('per_page')?:50))),($id=(int)$request->get_param('supplier_id'))>0?$id:null,sanitize_key((string)$request->get_param('severity'))));}
    public static function jobs(WP_REST_Request$request):WP_REST_Response{global$wpdb;$page=max(1,(int)$request->get_param('page'));$per=min(100,max(1,(int)($request->get_param('per_page')?:30)));$where=['1=1'];$values=[];$supplier=(int)$request->get_param('supplier_id');if($supplier>0){$where[]='supplier_id=%d';$values[]=$supplier;}$status=sanitize_key((string)$request->get_param('status'));if($status!==''){$where[]='status=%s';$values[]=$status;}$table=Schema::table('jobs');$ws=implode(' AND ',$where);$count="SELECT COUNT(*) FROM {$table} WHERE {$ws}";$total=(int)$wpdb->get_var($values?$wpdb->prepare($count,...$values):$count);$ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE {$ws} ORDER BY id DESC LIMIT %d OFFSET %d",...[...$values,$per,($page-1)*$per]));$repo=new ImportJobRepository();return new WP_REST_Response(['items'=>array_values(array_filter(array_map(fn($id)=>$repo->get((int)$id),$ids))),'page'=>$page,'perPage'=>$per,'total'=>$total]);}
    public static function diagnostics():WP_REST_Response{global$wpdb;$tables=[];foreach(['suppliers','catalog_products','jobs','logs']as$name){$table=Schema::table($name);$tables[$name]=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;}return new WP_REST_Response(['pluginVersion'=>PUPILOVO_SUPPLIER_HUB_VERSION,'schemaVersion'=>PUPILOVO_SUPPLIER_HUB_SCHEMA_VERSION,'phpVersion'=>PHP_VERSION,'wordpressVersion'=>get_bloginfo('version'),'woocommerceVersion'=>defined('WC_VERSION')?WC_VERSION:null,'actionSchedulerAvailable'=>function_exists('as_enqueue_async_action'),'xmlReaderAvailable'=>class_exists('XMLReader'),'sodiumAvailable'=>function_exists('sodium_crypto_secretbox'),'tables'=>$tables,'databaseError'=>$wpdb->last_error?:null]);}
}
