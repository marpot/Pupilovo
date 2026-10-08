<?php

namespace Pupilovo\SupplierHub\Infrastructure\Repository;

use Pupilovo\SupplierHub\Infrastructure\Database\Schema;

defined('ABSPATH') || exit;

final class FulfillmentRepository {
    public function links_for_products(array $product_ids): array {
        global $wpdb;
        $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));
        if ($product_ids === []) { return []; }
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $sql = 'SELECT l.id,l.supplier_id,l.catalog_product_id,l.external_id,l.wc_product_id,l.is_primary,s.name supplier_name
                FROM '.Schema::table('product_links').' l
                JOIN '.Schema::table('suppliers')." s ON s.id=l.supplier_id AND s.status<>'deleted'
                WHERE l.relationship_status='linked' AND l.wc_product_id IN ({$placeholders})
                ORDER BY l.is_primary DESC,l.id ASC";
        return $wpdb->get_results($wpdb->prepare($sql, ...$product_ids), ARRAY_A) ?: [];
    }

    /** @return array{id:int,created:bool} */
    public function ensure_group(int $order_id, ?int $supplier_id, ?string $supplier_name, string $currency, bool $manual): array {
        global $wpdb;
        $table = Schema::table('fulfillment_groups');
        $group_key = $supplier_id ? 'supplier:'.$supplier_id : 'manual';
        $now = current_time('mysql', true);
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (uuid,wc_order_id,group_key,supplier_id,supplier_name,status,requires_manual_decision,currency,created_at,updated_at)
             VALUES (%s,%d,%s,NULLIF(%d,0),NULLIF(%s,''),'pending',%d,%s,%s,%s)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)",
            wp_generate_uuid4(), $order_id, $group_key, $supplier_id ?? 0,
            $supplier_name ?? '', $manual ? 1 : 0, $currency, $now, $now
        ));
        if ($inserted === false) { throw new \RuntimeException('Nie udało się zapisać grupy realizacji dostawcy.'); }
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE wc_order_id=%d AND group_key=%s",
            $order_id, $group_key
        ));
        if ($id < 1) { throw new \RuntimeException('Nie udało się odczytać grupy realizacji dostawcy.'); }
        return ['id'=>$id,'created'=>$inserted===1];
    }

    public function add_item(int $group_id, array $item): bool {
        global $wpdb;
        $snapshot_json = wp_json_encode($item['snapshot'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($snapshot_json)) { throw new \RuntimeException('Nie udało się zakodować snapshotu pozycji zamówienia.'); }
        $result = $wpdb->query($wpdb->prepare(
            'INSERT INTO '.Schema::table('fulfillment_items').' (fulfillment_group_id,wc_order_id,wc_order_item_id,wc_product_id,wc_variation_id,supplier_id,product_link_id,quantity,assignment_reason,snapshot,snapshot_checksum,created_at)
             VALUES (%d,%d,%d,%d,%d,NULLIF(%d,0),NULLIF(%d,0),%s,%s,%s,%s,%s)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
            $group_id, $item['orderId'], $item['orderItemId'], $item['productId'], $item['variationId'],
            $item['supplierId'] ?? 0, $item['productLinkId'] ?? 0, (string) $item['quantity'],
            $item['assignmentReason'], $snapshot_json, hash('sha256', $snapshot_json), current_time('mysql', true)
        ));
        if ($result === false) { throw new \RuntimeException('Nie udało się zapisać snapshotu pozycji zamówienia.'); }
        return $result === 1;
    }

    public function find_item(int $order_id, int $order_item_id): ?array {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Schema::table('fulfillment_items').' WHERE wc_order_id=%d AND wc_order_item_id=%d',$order_id,$order_item_id),ARRAY_A);
        if(!is_array($row))return null;$record=$this->item_record($row);$record['fulfillmentGroupId']=(int)$row['fulfillment_group_id'];return$record;
    }

    public function refresh_group(int $group_id): void {
        global $wpdb;
        $items = Schema::table('fulfillment_items');
        $groups = Schema::table('fulfillment_groups');
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$groups} g SET
                g.item_count=(SELECT COUNT(*) FROM {$items} i WHERE i.fulfillment_group_id=g.id),
                g.total_quantity=(SELECT COALESCE(SUM(i.quantity),0) FROM {$items} i WHERE i.fulfillment_group_id=g.id),
                g.updated_at=%s WHERE g.id=%d",
            current_time('mysql', true), $group_id
        ));
        if ($result === false) { throw new \RuntimeException('Nie udało się przeliczyć grupy realizacji.'); }
    }

    public function add_history(int $group_id, string $event_key, string $event_type, string $message, array $data=[]): bool {
        global $wpdb;
        $context = $this->redact(is_array($data['context'] ?? null) ? $data['context'] : []);
        $result = $wpdb->query($wpdb->prepare(
            'INSERT INTO '.Schema::table('fulfillment_history').' (fulfillment_group_id,event_key,event_type,from_status,to_status,error_code,message,context,actor_user_id,created_at)
             VALUES (%d,%s,%s,NULLIF(%s,\'\'),NULLIF(%s,\'\'),NULLIF(%s,\'\'),%s,%s,%d,%s)
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
            $group_id, $event_key, sanitize_key($event_type), sanitize_key((string)($data['fromStatus']??'')),
            sanitize_key((string)($data['toStatus']??'')), sanitize_key((string)($data['errorCode']??'')),
            sanitize_text_field($message), wp_json_encode($context), (int)($data['actorUserId']??0), current_time('mysql', true)
        ));
        if ($result === false) { throw new \RuntimeException('Nie udało się zapisać historii realizacji.'); }
        return $result === 1;
    }

    public function note_assignment_error(int $group_id, string $code): void {
        global $wpdb;
        $result=$wpdb->query($wpdb->prepare(
            'UPDATE '.Schema::table('fulfillment_groups').' SET error_count=error_count+1,last_error_code=%s,updated_at=%s WHERE id=%d',
            sanitize_key($code), current_time('mysql', true), $group_id
        ));
        if($result===false)throw new \RuntimeException('Nie udało się zapisać błędu przypisania.');
    }

    public function paginate(array $query): array {
        global $wpdb;
        $page=max(1,(int)($query['page']??1));$per=min(100,max(1,(int)($query['perPage']??20)));
        $where=['1=1'];$values=[];
        if(!empty($query['supplierId'])){$where[]='supplier_id=%d';$values[]=(int)$query['supplierId'];}
        if(!empty($query['status'])){$where[]='status=%s';$values[]=(string)$query['status'];}
        if(isset($query['manual'])){$where[]='requires_manual_decision=%d';$values[]=$query['manual']?1:0;}
        $table=Schema::table('fulfillment_groups');$where_sql=implode(' AND ',$where);
        $count="SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";$total=(int)$wpdb->get_var($values?$wpdb->prepare($count,...$values):$count);
        $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d",...[...$values,$per,($page-1)*$per]),ARRAY_A)?:[];
        return['items'=>array_map([$this,'group_record'],$rows),'page'=>$page,'perPage'=>$per,'total'=>$total,'totalPages'=>max(1,(int)ceil($total/$per))];
    }

    public function for_order(int $order_id): array {
        global $wpdb;
        $groups=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Schema::table('fulfillment_groups').' WHERE wc_order_id=%d ORDER BY id',$order_id),ARRAY_A)?:[];
        return array_map(function(array$row)use($wpdb):array{$group=$this->group_record($row);$items=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Schema::table('fulfillment_items').' WHERE fulfillment_group_id=%d ORDER BY id',$row['id']),ARRAY_A)?:[];$history=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.Schema::table('fulfillment_history').' WHERE fulfillment_group_id=%d ORDER BY id',$row['id']),ARRAY_A)?:[];$group['items']=array_map([$this,'item_record'],$items);$group['history']=array_map([$this,'history_record'],$history);return$group;},$groups);
    }

    public function find_group(int $group_id): ?array {global$wpdb;$row=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.Schema::table('fulfillment_groups').' WHERE id=%d',$group_id),ARRAY_A);return is_array($row)?$this->group_record($row):null;}
    public function update_status(int$group_id,string$expected,string$next,?string$error_code=null):bool{global$wpdb;$table=Schema::table('fulfillment_groups');if($error_code!==null){$result=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status=%s,error_count=error_count+1,last_error_code=%s,updated_at=%s WHERE id=%d AND status=%s",$next,sanitize_key($error_code),current_time('mysql',true),$group_id,$expected));}else{$result=$wpdb->update($table,['status'=>$next,'updated_at'=>current_time('mysql',true)],['id'=>$group_id,'status'=>$expected],['%s','%s'],['%d','%s']);}return$result===1;}

    private function group_record(array $row): array {return['id'=>(int)$row['id'],'uuid'=>$row['uuid'],'orderId'=>(int)$row['wc_order_id'],'supplierId'=>$row['supplier_id']===null?null:(int)$row['supplier_id'],'supplierName'=>$row['supplier_name'],'status'=>$row['status'],'requiresManualDecision'=>(bool)$row['requires_manual_decision'],'itemCount'=>(int)$row['item_count'],'totalQuantity'=>(float)$row['total_quantity'],'currency'=>$row['currency'],'errorCount'=>(int)$row['error_count'],'lastErrorCode'=>$row['last_error_code'],'createdAt'=>$row['created_at'],'updatedAt'=>$row['updated_at']];}
    private function item_record(array $row): array {$snapshot=json_decode((string)$row['snapshot'],true);return['id'=>(int)$row['id'],'orderItemId'=>(int)$row['wc_order_item_id'],'productId'=>(int)$row['wc_product_id'],'variationId'=>(int)$row['wc_variation_id'],'supplierId'=>$row['supplier_id']===null?null:(int)$row['supplier_id'],'productLinkId'=>$row['product_link_id']===null?null:(int)$row['product_link_id'],'quantity'=>(float)$row['quantity'],'assignmentReason'=>$row['assignment_reason'],'snapshot'=>is_array($snapshot)?$snapshot:[],'snapshotChecksum'=>$row['snapshot_checksum'],'createdAt'=>$row['created_at']];}
    private function history_record(array $row): array {$context=json_decode((string)$row['context'],true);return['id'=>(int)$row['id'],'eventType'=>$row['event_type'],'fromStatus'=>$row['from_status'],'toStatus'=>$row['to_status'],'errorCode'=>$row['error_code'],'message'=>$row['message'],'context'=>is_array($context)?$context:[],'actorUserId'=>(int)$row['actor_user_id'],'createdAt'=>$row['created_at']];}
    private function redact(array $value): array {$blocked=['password','secret','token','authorization','api_key','apikey','ciphertext'];$walk=function(array$input)use(&$walk,$blocked):array{$out=[];foreach($input as$key=>$item){$safe_key=sanitize_key((string)$key);if(in_array(strtolower((string)$key),$blocked,true)){$out[$safe_key]='[REDACTED]';}elseif(is_array($item)){$out[$safe_key]=$walk($item);}elseif(is_scalar($item)||$item===null){$out[$safe_key]=$item;}}return$out;};return$walk($value);}
}
