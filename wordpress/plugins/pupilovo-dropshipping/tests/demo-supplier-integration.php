<?php
/** Isolated demo supplier end-to-end test. Never publishes products. */
if (PHP_SAPI !== 'cli') exit;
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Application\ImportExecutor;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SelectionRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\CategoryRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;
$checks=0; $supplier_id=0; $user_id=0; $terms=[]; $created=[];
function demo_check($yes,$msg){if(!$yes)throw new RuntimeException('FAIL '.$msg);++$GLOBALS['checks'];echo 'PASS '.$msg.PHP_EOL;}
try {
 global $wpdb;
 $run='demo-'.bin2hex(random_bytes(5));
 $user_id=wp_insert_user(['user_login'=>$run,'user_email'=>$run.'@example.test','user_pass'=>wp_generate_password(24),'role'=>'administrator']);
 if(is_wp_error($user_id))throw new RuntimeException($user_id->get_error_message());
 wp_set_current_user($user_id);
 $supplier=(new SupplierRepository())->create(['name'=>$run,'sourceType'=>'file_xml','adapterKey'=>'','status'=>'draft','sourceConfig'=>[],'fieldMapping'=>[]],$user_id);
 $supplier_id=(int)$supplier['id'];
 $mapping=['external_id'=>'id','sku'=>'sku','name'=>'name','purchase_price'=>'price','tax_rate'=>'tax','stock'=>'stock','categories'=>'category'];
 $base=['supplierId'=>$supplier_id,'mapping'=>$mapping,'declaredComplete'=>true];
 $svc=new CatalogIngestService();
 $xml=$svc->ingest($base+['file'=>__DIR__.'/fixtures/demo-pet-supplier.xml','format'=>'xml','recordPath'=>'catalog.product']);
 demo_check((int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.Schema::table('catalog_products').' WHERE supplier_id=%d',$supplier_id))>=30,'XML feed persists demo catalog');
 echo 'XML result: '.wp_json_encode($xml).PHP_EOL;
 $csv=$svc->ingest($base+['file'=>__DIR__.'/fixtures/demo-pet-supplier.csv','format'=>'csv','recordPath'=>'']);
 echo 'CSV result: '.wp_json_encode($csv).PHP_EOL;
 demo_check(($csv['unchanged']??0)>=30,'CSV matches existing XML catalog');
 $ids=array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT id FROM ".Schema::table('catalog_products')." WHERE supplier_id=%d AND external_id IN ('DEMO-020','DEMO-021','DEMO-022') ORDER BY id",$supplier_id)));
 demo_check(count($ids)===3,'three products available for selective import');
 $cats=$wpdb->get_results($wpdb->prepare('SELECT id,name FROM '.Schema::table('supplier_categories').' WHERE supplier_id=%d',$supplier_id),ARRAY_A);
 foreach($cats as $cat){$term=wp_insert_term($cat['name'].' '.$run,'product_cat');if(is_wp_error($term))throw new RuntimeException($term->get_error_message());$terms[]=(int)$term['term_id'];(new CategoryRepository())->save_mapping((int)$cat['id'],(int)$term['term_id'],'approved','manual',1.0,$user_id);}
 demo_check(count($cats)>=6,'supplier categories can be approved without touching existing categories');
 (new SelectionRepository())->set($ids,true,$user_id);
 (new PricingRuleRepository())->save($supplier_id,['scopeType'=>'supplier','priority'=>100,'active'=>true,'config'=>['mode'=>'markup','value'=>20,'purchasePriceIncludesTax'=>false,'rounding'=>['increment'=>0.01]]]);
 $req=new WP_REST_Request('POST','/pupilovo-supplier-hub/v1/import-preview');$req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode(['supplierId'=>$supplier_id]));
 $resp=rest_do_request($req);if(is_wp_error($resp))throw new RuntimeException($resp->get_error_message());
 demo_check($resp->get_status()===201,'dry run created');
 $plan=$resp->get_data();echo 'Preview: '.wp_json_encode($plan['job']['context']['summary']??[]).PHP_EOL; echo 'First item: '.wp_json_encode($plan['items'][0]??[]).PHP_EOL;
 demo_check((int)($plan['job']['context']['summary']['create']??0)===3,'only three selected products planned');
 $result=(new ImportExecutor())->execute((int)$plan['job']['id'],$user_id);
 demo_check((int)$result['job']['succeededItems']===3,'three selected products imported');
 $created=array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT wc_product_id FROM '.Schema::table('product_links').' WHERE supplier_id=%d',$supplier_id)));
 demo_check(count($created)===3 && count(array_filter($created,fn($id)=>wc_get_product($id)?->get_status()==='draft'))===3,'all demo products remain drafts');
 echo $checks.' demo integration checks passed.'.PHP_EOL;
} finally {
 global $wpdb;
 foreach($created as $id)wp_delete_post($id,true);
 foreach($terms as $id)wp_delete_term($id,'product_cat');
 if($supplier_id){
 $pids=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.Schema::table('catalog_products').' WHERE supplier_id=%d',$supplier_id));
 foreach($pids as $id)$wpdb->delete(Schema::table('product_history'),['catalog_product_id'=>(int)$id],['%d']);
 $jobs=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.Schema::table('jobs').' WHERE supplier_id=%d',$supplier_id));
 foreach($jobs as $id)$wpdb->delete(Schema::table('job_items'),['job_id'=>(int)$id],['%d']);
 foreach(['product_links','category_mappings','product_selections','pricing_rules','jobs','supplier_offers','catalog_products','supplier_categories','supplier_sources','secrets','feed_runs'] as $suffix)$wpdb->delete(Schema::table($suffix),['supplier_id'=>$supplier_id],['%d']);
 $wpdb->delete(Schema::table('suppliers'),['id'=>$supplier_id],['%d']);
 }
 if($user_id)wp_delete_user($user_id);
 wp_set_current_user(0);echo 'Demo fixtures cleaned up.'.PHP_EOL;
}
