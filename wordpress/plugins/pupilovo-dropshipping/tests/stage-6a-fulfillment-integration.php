<?php
/** Isolated order split, snapshot, idempotency and REST checks. */
if(PHP_SAPI!=='cli'){exit;}
require'/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Application\FulfillmentStatusService;
use Pupilovo\SupplierHub\Application\OrderFulfillmentSplitter;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\FulfillmentRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

$checks=0;$supplier_id=0;$user_id=0;$order_id=0;$product_ids=[];
function f_check($condition,string$label):void{if(!$condition)throw new RuntimeException('FAIL: '.$label);++$GLOBALS['checks'];echo'PASS: '.$label.PHP_EOL;}
function f_request(string$route):WP_REST_Response{$request=new WP_REST_Request('GET','/pupilovo-supplier-hub/v1'.$route);$response=rest_do_request($request);return is_wp_error($response)?rest_convert_error_to_response($response):$response;}
try{
    Schema::activate();global$wpdb;$run='psh-6a-'.bin2hex(random_bytes(5));
    $user_id=wp_insert_user(['user_login'=>$run,'user_email'=>$run.'@example.test','user_pass'=>wp_generate_password(24),'role'=>'administrator']);if(is_wp_error($user_id))throw new RuntimeException($user_id->get_error_message());wp_set_current_user($user_id);
    $supplier=(new SupplierRepository())->create(['name'=>$run,'sourceType'=>'file_json','adapterKey'=>'','status'=>'active','sourceConfig'=>[],'fieldMapping'=>[]],$user_id);$supplier_id=(int)$supplier['id'];
    $assigned=new WC_Product_Simple();$assigned->set_name('Produkt dostawcy '.$run);$assigned->set_status('draft');$assigned->set_regular_price('20');$assigned->set_sku($run.'-A');$assigned_id=(int)$assigned->save();$product_ids[]=$assigned_id;
    $manual=new WC_Product_Simple();$manual->set_name('Produkt ręczny '.$run);$manual->set_status('draft');$manual->set_regular_price('10');$manual_id=(int)$manual->save();$product_ids[]=$manual_id;
    $now=current_time('mysql',true);$external_id=$run.'-external';$wpdb->insert(Schema::table('product_links'),['supplier_id'=>$supplier_id,'catalog_product_id'=>999999,'external_id'=>$external_id,'wc_product_id'=>$assigned_id,'relationship_status'=>'linked','is_primary'=>1,'created_at'=>$now,'updated_at'=>$now]);$link_id=(int)$wpdb->insert_id;update_post_meta($assigned_id,'_pupilovo_supplier_id',$supplier_id);update_post_meta($assigned_id,'_pupilovo_supplier_external_id',$external_id);
    $order=wc_create_order();if(is_wp_error($order))throw new RuntimeException($order->get_error_message());$order_id=(int)$order->get_id();$assigned_item_id=$order->add_product($assigned,2);$manual_item_id=$order->add_product($manual,1);$order->calculate_totals();$order->save();
    $splitter=new OrderFulfillmentSplitter();$first=$splitter->split($order);$second=$splitter->split($order);
    f_check($first['groups']===2&&$first['manualItems']<=1,'order is divided into supplier and manual groups');
    f_check($second['itemsCreated']===0&&$second['manualItems']===0,'reprocessing is idempotent');
    $repository=new FulfillmentRepository();$groups=$repository->for_order($order_id);
    f_check(count($groups)===2,'exactly two fulfillment groups persist');
    $supplier_group=current(array_filter($groups,fn($group)=>$group['supplierId']===$supplier_id));$manual_group=current(array_filter($groups,fn($group)=>$group['requiresManualDecision']));
    f_check(is_array($supplier_group)&&$supplier_group['itemCount']===1&&$supplier_group['totalQuantity']===2.0,'supplier group stores item and quantity totals');
    f_check(is_array($manual_group)&&$manual_group['itemCount']===1&&$manual_group['lastErrorCode']==='missing_supplier_link','unlinked product requires a manual decision');
    $supplier_item=$supplier_group['items'][0];
    f_check($supplier_item['orderItemId']===(int)$assigned_item_id&&$supplier_item['productLinkId']===$link_id,'snapshot stores order item and product link identifiers');
    f_check(is_numeric($supplier_item['snapshot']['quantity'])&&(float)$supplier_item['snapshot']['quantity']===2.0&&(int)$supplier_item['snapshot']['supplierId']===$supplier_id,'snapshot stores immutable quantity and supplier identifier');
    $original_name=$supplier_item['snapshot']['productName'];$assigned->set_name('Zmieniona nazwa '.$run);$assigned->save();$splitter->split($order);$after=$repository->for_order($order_id);$after_supplier=current(array_filter($after,fn($group)=>$group['supplierId']===$supplier_id));
    f_check($after_supplier['items'][0]['snapshot']['productName']===$original_name,'catalog/product changes do not rewrite the order snapshot');
    $ready=(new FulfillmentStatusService())->transition((int)$supplier_group['id'],'ready',$user_id);
    f_check($ready['status']==='ready','internal supplier status can transition to ready');
    $after_transition=$repository->for_order($order_id);$ready_group=current(array_filter($after_transition,fn($group)=>$group['supplierId']===$supplier_id));
    f_check(count($ready_group['history'])===2,'status transition appends history without replacing creation event');
    $rest=f_request('/fulfillment/orders/'.$order_id);f_check($rest->get_status()===200&&count($rest->get_data()['groups'])===2,'authorized REST returns supplier groups and snapshots');
    wp_set_current_user(0);$denied=f_request('/fulfillment/orders');f_check(in_array($denied->get_status(),[401,403],true),'unauthorized REST access is denied');
    echo$checks.' Stage 6A fulfillment integration checks passed.'.PHP_EOL;
}finally{
    global$wpdb;wp_set_current_user($user_id);
    if($order_id>0){$group_ids=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.Schema::table('fulfillment_groups').' WHERE wc_order_id=%d',$order_id));foreach($group_ids as$id){$wpdb->delete(Schema::table('fulfillment_history'),['fulfillment_group_id'=>(int)$id],['%d']);$wpdb->delete(Schema::table('fulfillment_items'),['fulfillment_group_id'=>(int)$id],['%d']);}$wpdb->delete(Schema::table('fulfillment_groups'),['wc_order_id'=>$order_id],['%d']);$order=wc_get_order($order_id);if($order)$order->delete(true);}
    foreach($product_ids as$id)wp_delete_post((int)$id,true);
    if($supplier_id>0){$wpdb->delete(Schema::table('product_links'),['supplier_id'=>$supplier_id],['%d']);foreach(['supplier_sources','secrets']as$suffix)$wpdb->delete(Schema::table($suffix),['supplier_id'=>$supplier_id],['%d']);$wpdb->delete(Schema::table('suppliers'),['id'=>$supplier_id],['%d']);}
    if($user_id>0)wp_delete_user($user_id);wp_set_current_user(0);echo'Stage 6A fixtures cleaned up.'.PHP_EOL;
}
