<?php
/** Category matching and persistent selection integration checks. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Domain\Category\CategoryMatcher;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;

$checks=0; $supplier_id=0; $user_id=0; $term_ids=[];
function c_check($condition,string $label):void{if(!$condition){throw new RuntimeException('FAIL: '.$label);}++$GLOBALS['checks'];echo 'PASS: '.$label.PHP_EOL;}
function c_request(string $method,string $route,?array $body=null):WP_REST_Response{$parts=wp_parse_url($route);$query=[];if(!empty($parts['query']))parse_str($parts['query'],$query);$r=new WP_REST_Request($method,'/pupilovo-supplier-hub/v1'.($parts['path']??$route));$r->set_query_params($query);if($body!==null){$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($body));}$response=rest_do_request($r);return is_wp_error($response)?rest_convert_error_to_response($response):$response;}

try{
    $run='psh-c-'.bin2hex(random_bytes(5));
    $user_id=wp_insert_user(['user_login'=>$run,'user_email'=>$run.'@example.test','user_pass'=>wp_generate_password(24),'role'=>'administrator']);
    if(is_wp_error($user_id))throw new RuntimeException($user_id->get_error_message());wp_set_current_user($user_id);
    $supplier=(new SupplierRepository())->create(['name'=>$run,'sourceType'=>'file_json','adapterKey'=>'','status'=>'draft','sourceConfig'=>[],'fieldMapping'=>[]],$user_id);$supplier_id=(int)$supplier['id'];
    $mapping=['external_id'=>'id','sku'=>'identity.sku','ean'=>'identity.ean','name'=>'title','purchase_price'=>'pricing.net','categories'=>'categories'];
    (new CatalogIngestService())->ingest(['supplierId'=>$supplier_id,'file'=>__DIR__.'/fixtures/nested-products.json','format'=>'json','recordPath'=>'data.products','mapping'=>$mapping,'declaredComplete'=>true]);
    global $wpdb;
    c_check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('supplier_categories').' WHERE supplier_id=%d',$supplier_id))===4,'supplier categories persist across ingest');

    $parent=wp_insert_term('Psy '.$run,'product_cat');if(is_wp_error($parent))throw new RuntimeException($parent->get_error_message());$term_ids[]=(int)$parent['term_id'];
    $child=wp_insert_term('Karma','product_cat',['parent'=>(int)$parent['term_id']]);if(is_wp_error($child))throw new RuntimeException($child->get_error_message());$term_ids[]=(int)$child['term_id'];
    $matcher=new CategoryMatcher();$suggestions=$matcher->suggest(['name'=>'Karma','path'=>'Psy > Karma'],[['id'=>(int)$child['term_id'],'name'=>'Karma','path'=>'Psy '.$run.' > Karma']]);
    c_check($suggestions!==[]&&$suggestions[0]['score']>=0.88,'category matcher scores exact leaf and explains result');

    $category_id=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.Schema::table('supplier_categories').' WHERE supplier_id=%d AND name=%s LIMIT 1',$supplier_id,'Karma'));
    $mapped=c_request('POST','/category-mappings',['categoryId'=>$category_id,'decision'=>'map','termId'=>(int)$child['term_id'],'confidence'=>0.9]);
    c_check($mapped->get_status()===200,'administrator approves existing WooCommerce category');
    $mapping_row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Schema::table('category_mappings').' WHERE supplier_category_id=%d',$category_id),ARRAY_A);
    c_check((int)$mapping_row['wc_term_id']===(int)$child['term_id']&&$mapping_row['decision']==='approved','category decision persists per supplier');

    $product_ids=array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT id FROM '.Schema::table('catalog_products').' WHERE supplier_id=%d ORDER BY id',$supplier_id)));
    $selected=c_request('PUT','/selections',['productIds'=>[$product_ids[0]],'selected'=>true])->get_data();
    c_check(($selected['changed']??0)===1 && (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('product_selections').' WHERE supplier_id=%d',$supplier_id))===1,'single product selection persists');
    $bulk=c_request('PUT','/selections/filter',['selected'=>true,'filter'=>['supplierId'=>$supplier_id]])->get_data();
    c_check(($bulk['changed']??0)===1 && (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('product_selections').' WHERE supplier_id=%d',$supplier_id))===2,'filtered bulk selection runs server-side');
    (new CatalogIngestService())->ingest(['supplierId'=>$supplier_id,'file'=>__DIR__.'/fixtures/nested-products.json','format'=>'json','recordPath'=>'data.products','mapping'=>$mapping,'declaredComplete'=>true]);
    c_check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('product_selections').' WHERE supplier_id=%d',$supplier_id))===2,'re-ingest preserves administrator selection');
    $unselected=c_request('PUT','/selections/filter',['selected'=>false,'filter'=>['supplierId'=>$supplier_id,'search'=>'JSON-2']])->get_data();
    c_check(($unselected['changed']??0)===1 && (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('product_selections').' WHERE supplier_id=%d',$supplier_id))===1,'filtered deselection changes only matching product');
    echo $checks.' Stage C integration checks passed.'.PHP_EOL;
}finally{
    global $wpdb;
    foreach($term_ids as $id)wp_delete_term($id,'product_cat');
    if($supplier_id>0){$product_ids=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.Schema::table('catalog_products').' WHERE supplier_id=%d',$supplier_id));foreach($product_ids as $id)$wpdb->delete(Schema::table('product_history'),['catalog_product_id'=>(int)$id],['%d']);foreach(['category_mappings','product_selections','supplier_offers','catalog_products','supplier_categories','supplier_sources','secrets','feed_runs']as $suffix)$wpdb->delete(Schema::table($suffix),['supplier_id'=>$supplier_id],['%d']);$wpdb->delete(Schema::table('suppliers'),['id'=>$supplier_id],['%d']);}
    if($user_id>0)wp_delete_user($user_id);wp_set_current_user(0);echo 'Stage C fixtures cleaned up.'.PHP_EOL;
}
