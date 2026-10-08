<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class ProductMatcher {
    public function match(array $catalog): array {
        global $wpdb;
        $link=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Schema::table('product_links').' WHERE supplier_id=%d AND external_id=%s',(int)$catalog['supplier_id'],(string)$catalog['external_id']),ARRAY_A);
        if($link){$product=wc_get_product((int)$link['wc_product_id']);return $product?['action'=>'update','wcProductId'=>$product->get_id(),'matchedBy'=>'supplier_link','confidence'=>1.0]:['action'=>'conflict','reason'=>'linked_product_missing','wcProductId'=>(int)$link['wc_product_id']];}
        $candidates=[];
        if(!empty($catalog['sku'])){$id=wc_get_product_id_by_sku((string)$catalog['sku']);if($id)$candidates[$id][]='sku';}
        if(!empty($catalog['ean'])){
            $ids=get_posts(['post_type'=>['product','product_variation'],'post_status'=>'any','fields'=>'ids','posts_per_page'=>10,'meta_query'=>['relation'=>'OR',['key'=>'_global_unique_id','value'=>$catalog['ean'],'compare'=>'='],['key'=>'_ean','value'=>$catalog['ean'],'compare'=>'='],['key'=>'gtin','value'=>$catalog['ean'],'compare'=>'=']]]);
            foreach($ids as $id)$candidates[(int)$id][]='ean';
        }
        if($candidates===[])return ['action'=>'create','matchedBy'=>null,'confidence'=>1.0];
        if(count($candidates)>1)return ['action'=>'conflict','reason'=>'multiple_identifier_matches','candidates'=>array_map(static fn($reasons,$id)=>['wcProductId'=>(int)$id,'matchedBy'=>$reasons],$candidates,array_keys($candidates))];
        $id=(int)array_key_first($candidates);
        return ['action'=>'conflict','reason'=>'unlinked_existing_product','candidates'=>[['wcProductId'=>$id,'matchedBy'=>$candidates[$id]]]];
    }
}
