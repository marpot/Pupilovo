<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class PricingRuleRepository {
    public function list(int $supplier_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM '.Schema::table('pricing_rules').' WHERE supplier_id=%d ORDER BY priority ASC,id ASC',$supplier_id),ARRAY_A)?:[];
        return array_map([$this,'record'],$rows);
    }

    public function save(int $supplier_id,array $input): array {
        global $wpdb;
        $scope=in_array($input['scopeType']??'supplier',['supplier','category','product'],true)?$input['scopeType']:'supplier';
        $scope_id=$scope==='supplier'?null:max(1,(int)($input['scopeId']??0));
        $config=is_array($input['config']??null)?$input['config']:[];
        $now=current_time('mysql',true);$table=Schema::table('pricing_rules');$id=(int)($input['id']??0);
        $data=['supplier_id'=>$supplier_id,'scope_type'=>$scope,'scope_id'=>$scope_id,'priority'=>(int)($input['priority']??100),'active'=>!empty($input['active'])?1:0,'rule_config'=>wp_json_encode($config),'updated_at'=>$now];
        if($id>0){$result=$wpdb->update($table,$data,['id'=>$id,'supplier_id'=>$supplier_id],null,['%d','%d']);}
        else{$result=$wpdb->insert($table,['created_at'=>$now,...$data],null);$id=(int)$wpdb->insert_id;}
        if($result===false)throw new \RuntimeException('Nie udało się zapisać reguły cenowej.');
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d",$id),ARRAY_A);
        return $this->record($row);
    }

    public function delete(int $supplier_id,int $id): bool {
        global $wpdb;return $wpdb->delete(Schema::table('pricing_rules'),['id'=>$id,'supplier_id'=>$supplier_id],['%d','%d'])>0;
    }

    public function resolve(int $supplier_id,int $product_id,array $category_ids=[]): ?array {
        $rules=$this->list($supplier_id);$applicable=[];
        foreach($rules as $rule){if(!$rule['active'])continue;$ok=$rule['scopeType']==='supplier'||($rule['scopeType']==='product'&&$rule['scopeId']===$product_id)||($rule['scopeType']==='category'&&in_array($rule['scopeId'],$category_ids,true));if($ok)$applicable[]=$rule;}
        usort($applicable,static function(array $a,array $b):int{$priority=$a['priority']<=>$b['priority'];if($priority!==0)return $priority;$specificity=['supplier'=>3,'category'=>2,'product'=>1];return $specificity[$a['scopeType']]<=>$specificity[$b['scopeType']];});
        return $applicable[0]??null;
    }

    public function hash_for_supplier(int $supplier_id): string {return hash('sha256',wp_json_encode($this->list($supplier_id)));}

    private function record(array $row): array {$config=json_decode((string)$row['rule_config'],true);return ['id'=>(int)$row['id'],'supplierId'=>(int)$row['supplier_id'],'scopeType'=>$row['scope_type'],'scopeId'=>$row['scope_id']===null?null:(int)$row['scope_id'],'priority'=>(int)$row['priority'],'active'=>(bool)$row['active'],'config'=>is_array($config)?$config:[],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at']];}
}
