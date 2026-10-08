<?php
/** Pure fulfillment assignment/status checks. */
if (PHP_SAPI !== 'cli') { exit; }
require '/var/www/html/wp-load.php';

use Pupilovo\SupplierHub\Domain\Fulfillment\FulfillmentStatus;
use Pupilovo\SupplierHub\Domain\Fulfillment\SupplierAssignmentResolver;

$checks=0;
function fulfillment_unit_check($condition,string$label):void{if(!$condition)throw new RuntimeException('FAIL: '.$label);++$GLOBALS['checks'];echo'PASS: '.$label.PHP_EOL;}

$resolver=new SupplierAssignmentResolver();
$links=[['id'=>11,'supplier_id'=>3,'external_id'=>'a','is_primary'=>0],['id'=>12,'supplier_id'=>4,'external_id'=>'b','is_primary'=>1]];
$explicit=$resolver->resolve(3,$links);
fulfillment_unit_check($explicit['supplierId']===3&&$explicit['productLinkId']===11&&!$explicit['requiresManualDecision'],'verified product metadata wins');
$primary=$resolver->resolve(null,$links);
fulfillment_unit_check($primary['supplierId']===4&&$primary['reason']==='primary_product_link','single primary link wins');
$single=$resolver->resolve(null,[['id'=>15,'supplier_id'=>7,'external_id'=>'c','is_primary'=>0]]);
fulfillment_unit_check($single['supplierId']===7&&$single['reason']==='single_product_link','single link is assigned');
$missing=$resolver->resolve(null,[]);
fulfillment_unit_check($missing['requiresManualDecision']&&$missing['reason']==='missing_supplier_link','missing link requires manual decision');
$ambiguous=$resolver->resolve(null,[['id'=>1,'supplier_id'=>1,'external_id'=>'x','is_primary'=>0],['id'=>2,'supplier_id'=>2,'external_id'=>'y','is_primary'=>0]]);
fulfillment_unit_check($ambiguous['requiresManualDecision']&&$ambiguous['reason']==='ambiguous_supplier_links','ambiguous links require manual decision');
$stale=$resolver->resolve(99,$links);
fulfillment_unit_check($stale['requiresManualDecision']&&$stale['reason']==='supplier_metadata_without_active_link','stale supplier metadata is not trusted');
fulfillment_unit_check(FulfillmentStatus::all()===['pending','ready','manually_approved','sent','acknowledged','shipped','delivered','failed','cancelled'],'all required statuses are defined');
foreach(FulfillmentStatus::all()as$status)fulfillment_unit_check(FulfillmentStatus::is_valid($status),'valid status: '.$status);
fulfillment_unit_check(!FulfillmentStatus::is_valid('paid'),'unrelated WooCommerce status is rejected');
echo$checks.' fulfillment unit checks passed.'.PHP_EOL;
