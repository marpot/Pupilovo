<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Application\SyncManager;
use Pupilovo\SupplierHub\Application\SyncScheduler;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class SyncRestController {
    private const NS='pupilovo-supplier-hub/v1';
    public static function register_routes():void{
        register_rest_route(self::NS,'/sync',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'manual'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
        register_rest_route(self::NS,'/suppliers/(?P<id>\d+)/schedule',['methods'=>WP_REST_Server::EDITABLE,'callback'=>[self::class,'schedule'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
    }
    public static function manual(WP_REST_Request$request){$body=$request->get_json_params();$body=is_array($body)?$body:[];try{return new WP_REST_Response((new SyncManager())->enqueue((int)($body['supplierId']??0),get_current_user_id(),is_array($body['managedFields']??null)?$body['managedFields']:['price','stock']),202);}catch(\InvalidArgumentException$error){return new WP_Error('invalid_sync',$error->getMessage(),['status'=>400]);}catch(\Throwable$error){return new WP_Error('sync_conflict',$error->getMessage(),['status'=>409]);}}
    public static function schedule(WP_REST_Request$request){$body=$request->get_json_params();$body=is_array($body)?$body:[];try{return new WP_REST_Response((new SyncScheduler())->configure((int)$request['id'],rest_sanitize_boolean($body['enabled']??false),(int)($body['intervalSeconds']??86400),is_array($body['managedFields']??null)?$body['managedFields']:['price','stock'],get_current_user_id()));}catch(\InvalidArgumentException$error){return new WP_Error('invalid_schedule',$error->getMessage(),['status'=>404]);}catch(\Throwable$error){return new WP_Error('schedule_failed',$error->getMessage(),['status'=>500]);}}
}
