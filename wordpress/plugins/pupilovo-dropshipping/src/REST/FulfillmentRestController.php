<?php

namespace Pupilovo\SupplierHub\REST;

use Pupilovo\SupplierHub\Domain\Fulfillment\FulfillmentStatus;
use Pupilovo\SupplierHub\Infrastructure\Repository\FulfillmentRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

final class FulfillmentRestController {
    private const NS='pupilovo-supplier-hub/v1';

    public static function register_routes():void{
        register_rest_route(self::NS,'/fulfillment/orders',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'orders'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
        register_rest_route(self::NS,'/fulfillment/orders/(?P<id>\d+)',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'order'],'permission_callback'=>[AdminRestController::class,'can_manage']]);
    }

    public static function orders(WP_REST_Request$request){$status=sanitize_key((string)$request->get_param('status'));if($status!==''&&!FulfillmentStatus::is_valid($status))return new WP_Error('invalid_fulfillment_status','Nieprawidłowy status realizacji.',['status'=>400]);$manual=$request->get_param('manual');$query=['page'=>max(1,(int)$request->get_param('page')),'perPage'=>min(100,max(1,(int)($request->get_param('per_page')?:20))),'supplierId'=>(int)$request->get_param('supplier_id'),'status'=>$status];if($manual!==null&&$manual!=='')$query['manual']=rest_sanitize_boolean($manual);return new WP_REST_Response((new FulfillmentRepository())->paginate($query));}
    public static function order(WP_REST_Request$request){$order_id=(int)$request['id'];if(!wc_get_order($order_id))return new WP_Error('order_not_found','Nie znaleziono zamówienia WooCommerce.',['status'=>404]);return new WP_REST_Response(['orderId'=>$order_id,'groups'=>(new FulfillmentRepository())->for_order($order_id)]);}
}
