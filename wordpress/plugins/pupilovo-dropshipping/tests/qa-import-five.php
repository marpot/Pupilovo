<?php
/** One-time QA demo import: only five selected products, drafts only. */
if (PHP_SAPI !== 'cli') exit(1);
require '/var/www/html/wp-load.php';
use Pupilovo\SupplierHub\Application\CatalogIngestService;
use Pupilovo\SupplierHub\Application\ImportExecutor;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\SupplierRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\CategoryRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\SelectionRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;
$wanted=['DEMO-005','DEMO-006','DEMO-007','DEMO-008','DEMO-019'];
$cats=['Zabawki','Legowiska','Miski i poidła'];
$sku=array_map(fn($x)=>'PUP-'.$x,$wanted);
foreach($sku as $s){if(wc_get_product_id_by_sku($s)){echo "ABORT existing SKU $s\n";exit(2);}}
foreach($cats as $name){if(!get_term_by('name',$name,'product_cat')){echo "ABORT missing category $name\n";exit(2);}}
$user=get_users(['role'=>'administrator','number'=>1])[0]??null;
if(!$user){echo "ABORT no admin\n";exit(2);}
wp_set_current_user($user->ID);
$supplier=(new SupplierRepository())->create(['name'=>'Pupilovo QA demo 5 '.gmdate('Ymd-His'),'sourceType'=>'file_csv','adapterKey'=>'','status'=>'draft','sourceConfig'=>[],'fieldMapping'=>[]],$user->ID);
$id=(int)$supplier['id'];
echo "SUPPLIER $id\n";
$mapping=['external_id'=>'id','sku'=>'sku','name'=>'name','purchase_price'=>'price','tax_rate'=>'tax','stock'=>'stock','categories'=>'category'];
$ingest=(new CatalogIngestService())->ingest(['supplierId'=>$id,'file'=>__DIR__.'/fixtures/demo-pet-supplier.csv','format'=>'csv','recordPath'=>'','mapping'=>$mapping,'declaredComplete'=>true]);
echo 'INGEST '.wp_json_encode(['valid'=>$ingest['valid']??null,'invalid'=>$ingest['invalid']??null])."\n";
if(($ingest['valid']??0)!==33 || ($ingest['invalid']??1)!==0)exit(3);
global $wpdb;
foreach($cats as $name){
$catid=(int)$wpdb->get_var($wpdb->prepare('SELECT id FROM '.Schema::table('supplier_categories').' WHERE supplier_id=%d AND name=%s',$id,$name));
$term=get_term_by('name',$name,'product_cat');
if(!$catid || !$term)exit(3);
(new CategoryRepository())->save_mapping($catid,(int)$term->term_id,'approved','manual',1.0,$user->ID);
}
$placeholders=implode(',',array_fill(0,count($wanted),'%s'));
$ids=array_map('intval',$wpdb->get_col($wpdb->prepare('SELECT id FROM '.Schema::table('catalog_products')." WHERE supplier_id=%d AND external_id IN ($placeholders)",array_merge([$id],$wanted))));
if(count($ids)!==5){echo "ABORT selected count ".count($ids)."\n";exit(3);}
(new SelectionRepository())->set($ids,true,$user->ID);
(new PricingRuleRepository())->save($id,['scopeType'=>'supplier','priority'=>100,'active'=>true,'config'=>['mode'=>'markup','value'=>20,'purchasePriceIncludesTax'=>false,'rounding'=>['increment'=>0.01]]]);
$req=new WP_REST_Request('POST','/pupilovo-supplier-hub/v1/import-preview');
$req->set_header('Content-Type','application/json');$req->set_body(wp_json_encode(['supplierId'=>$id]));
$res=rest_do_request($req);
if(is_wp_error($res)||$res->get_status()!==201){echo "ABORT preview error\n";exit(4);}
$plan=$res->get_data();$summary=$plan['job']['context']['summary']??[];
echo 'PREVIEW '.wp_json_encode($summary)."\n";
if(($summary['create']??0)!==5||($summary['conflict']??0)!==0||($summary['update']??0)!==0||($summary['skip']??0)!==0){echo "ABORT unsafe preview\n";exit(4);}
foreach($plan['items'] as $item){if(($item['status']??'')!=='ready'||($item['action']??'')!=='create'||!empty($item['after']['errors'])||count($item['after']['categoryTermIds']??[])!==1){echo "ABORT item not ready\n";exit(4);}}
$result=(new ImportExecutor())->execute((int)$plan['job']['id'],$user->ID);
echo 'RESULT '.wp_json_encode(['status'=>$result['job']['status'],'succeeded'=>$result['job']['succeededItems'],'failed'=>$result['job']['failedItems']])."\n";
$pass=($result['job']['succeededItems']??0)===5 && ($result['job']['failedItems']??1)===0;
foreach($wanted as $external){$sku='PUP-'.$external;$pid=wc_get_product_id_by_sku($sku);$p=$pid?wc_get_product($pid):null;$ok=$p && $p->get_status()==='draft' && count($p->get_category_ids())===1;$pass=$pass&&$ok;echo ($ok?'PASS ':'FAIL ').$external.' product='.($pid?:0).' status='.($p?$p->get_status():'missing').' price='.($p?$p->get_regular_price():'-')."\n";}
echo $pass?"SUCCESS five drafts imported\n":"FAIL validation\n";
exit($pass?0:5);
