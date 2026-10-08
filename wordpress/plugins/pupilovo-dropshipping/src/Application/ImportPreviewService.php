<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Domain\Pricing\PricingCalculator;
use Pupilovo\SupplierHub\Infrastructure\Database\Schema;
use Pupilovo\SupplierHub\Infrastructure\Repository\ImportJobRepository;
use Pupilovo\SupplierHub\Infrastructure\Repository\PricingRuleRepository;

defined('ABSPATH') || exit;

final class ImportPreviewService {
    public function __construct(
        private readonly PricingCalculator $pricing = new PricingCalculator(),
        private readonly ProductMatcher $matcher = new ProductMatcher(),
        private readonly PricingRuleRepository $rules = new PricingRuleRepository(),
        private readonly ImportJobRepository $jobs = new ImportJobRepository()
    ) {}

    public function create(?int $supplier_id,int $user_id,array $options=[]): array {
        global $wpdb;
        $catalog=Schema::table('catalog_products');$selections=Schema::table('product_selections');
        $where=$supplier_id? $wpdb->prepare(' AND p.supplier_id=%d',$supplier_id):'';
        if(!empty($options['linkedOnly'])){$where.=' AND EXISTS (SELECT 1 FROM '.Schema::table('product_links').' l WHERE l.catalog_product_id=p.id AND l.supplier_id=p.supplier_id AND l.relationship_status=\'linked\')';}
        $rows=$wpdb->get_results("SELECT p.* FROM {$catalog} p JOIN {$selections} sel ON sel.catalog_product_id=p.id WHERE p.record_status='valid' {$where} ORDER BY p.id",ARRAY_A)?:[];
        if($rows===[])throw new \RuntimeException('Nie wybrano żadnych produktów do importu.');
        $supplier_ids=array_values(array_unique(array_map(static fn(array $row):int=>(int)$row['supplier_id'],$rows)));
        $pricing_hashes=[];foreach($supplier_ids as $id)$pricing_hashes[$id]=$this->rules->hash_for_supplier($id);
        $snapshot_parts=array_map(static fn(array $row):string=>$row['id'].':'.$row['version'].':'.$row['checksum'],$rows);
        $context=['catalogSnapshotHash'=>hash('sha256',implode('|',$snapshot_parts)),'pricingRuleHashes'=>$pricing_hashes,'publishStatus'=>'draft','managedFields'=>$options['managedFields']??['name','description','short_description','price','stock','categories','attributes','images','weight','dimensions'],'createdAt'=>gmdate('c')];
        $job_id=$this->jobs->create_plan($supplier_id,$context,$user_id);
        $summary=['create'=>0,'update'=>0,'conflict'=>0,'skip'=>0];
        foreach($rows as $row){
            $payload=json_decode((string)$row['normalized_payload'],true);$payload=is_array($payload)?$payload:[];
            $category=$this->category_terms((int)$row['supplier_id'],$payload['categories']??[]);
            $rule=$this->rules->resolve((int)$row['supplier_id'],(int)$row['id'],$category['categoryIds']);
            $price=$rule?$this->pricing->calculate(['purchasePrice'=>$row['purchase_price'],'currency'=>$row['currency'],'taxRate'=>$row['tax_rate'],'priceIncludesTax'=>!empty($rule['config']['purchasePriceIncludesTax'])],$rule['config'],get_woocommerce_currency()):['status'=>'decision_required','errors'=>['missing_pricing_rule']];
            $match=$this->matcher->match($row);$errors=[];
            if($price['status']!=='calculated')$errors=array_merge($errors,$price['errors']??['pricing_failed']);
            if($category['unmapped']!==[])$errors[]='unmapped_categories';
            if($match['action']==='conflict')$errors[]=$match['reason'];
            $action=$errors===[]?$match['action']:'conflict';$summary[$action]++;
            $before=[];
            if(!empty($match['wcProductId'])){$product=wc_get_product((int)$match['wcProductId']);if($product)$before=['wcProductId'=>$product->get_id(),'name'=>$product->get_name(),'sku'=>$product->get_sku(),'price'=>$product->get_regular_price(),'stock'=>$product->get_stock_quantity(),'modifiedGmt'=>$product->get_date_modified()?->date('c')];}
            $after=['catalogProductId'=>(int)$row['id'],'catalogVersion'=>(int)$row['version'],'catalogChecksum'=>$row['checksum'],'supplierId'=>(int)$row['supplier_id'],'externalId'=>$row['external_id'],'product'=>$payload,'pricing'=>$price,'pricingRule'=>$rule,'categoryTermIds'=>$category['termIds'],'unmappedCategories'=>$category['unmapped'],'match'=>$match,'errors'=>$errors,'managedFields'=>$context['managedFields']];
            $this->jobs->add_item($job_id,(int)$row['id'],$action,$action==='conflict'?'blocked':'ready',$before,$after,$errors[0]??null);
        }
        $this->jobs->finish_plan($job_id,$summary);
        return ['job'=>$this->jobs->get($job_id),'items'=>$this->jobs->items($job_id)];
    }

    private function category_terms(int $supplier_id,array $categories): array {
        global $wpdb;$term_ids=[];$category_ids=[];$unmapped=[];
        foreach($categories as $category){$row=$wpdb->get_row($wpdb->prepare('SELECT c.id,m.wc_term_id,m.decision FROM '.Schema::table('supplier_categories').' c LEFT JOIN '.Schema::table('category_mappings').' m ON m.supplier_category_id=c.id WHERE c.supplier_id=%d AND (c.path=%s OR c.name=%s) ORDER BY LENGTH(c.path) DESC LIMIT 1',$supplier_id,(string)$category,(string)$category),ARRAY_A);if(!$row||$row['decision']!=='approved'||!(int)$row['wc_term_id']){$unmapped[]=$category;continue;}$category_ids[]=(int)$row['id'];$term_ids[]=(int)$row['wc_term_id'];}
        return ['termIds'=>array_values(array_unique($term_ids)),'categoryIds'=>$category_ids,'unmapped'=>$unmapped];
    }
}
