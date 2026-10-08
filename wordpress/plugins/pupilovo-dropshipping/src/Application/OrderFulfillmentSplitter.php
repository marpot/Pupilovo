<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Domain\Fulfillment\SupplierAssignmentResolver;
use Pupilovo\SupplierHub\Infrastructure\Repository\FulfillmentRepository;

defined('ABSPATH') || exit;

final class OrderFulfillmentSplitter {
    public function __construct(
        private readonly SupplierAssignmentResolver $resolver = new SupplierAssignmentResolver(),
        private readonly FulfillmentRepository $repository = new FulfillmentRepository()
    ) {}

    /** @return array{orderId:int,groups:int,itemsCreated:int,manualItems:int} */
    public function split($order): array {
        if (is_numeric($order)) { $order = wc_get_order((int)$order); }
        if (!$order instanceof \WC_Order) { throw new \InvalidArgumentException('Nie znaleziono zamówienia WooCommerce.'); }
        $order_id=(int)$order->get_id();if($order_id<1)throw new \InvalidArgumentException('Zamówienie musi być zapisane przed podziałem.');
        $group_ids=[];$created=0;$manual=0;
        foreach($order->get_items('line_item')as$item_id=>$item){
                if(!$item instanceof \WC_Order_Item_Product)continue;
                $existing=$this->repository->find_item($order_id,(int)$item_id);if($existing){$group_ids[$existing['fulfillmentGroupId']]=true;continue;}
                $product_id=(int)$item->get_product_id();$variation_id=(int)$item->get_variation_id();$lookup_ids=array_values(array_unique(array_filter([$variation_id,$product_id])));
                $links=$this->repository->links_for_products($lookup_ids);$metadata=$this->supplier_metadata($variation_id,$product_id);$assignment=$this->resolver->resolve($metadata['supplierId'],$links,$metadata['externalId']);
                $selected_link=null;foreach($links as$link){if((int)$link['id']===(int)($assignment['productLinkId']??0)){$selected_link=$link;break;}}
                $supplier_name=$selected_link['supplier_name']??null;$group=$this->repository->ensure_group($order_id,$assignment['supplierId'],$supplier_name,(string)$order->get_currency(),$assignment['requiresManualDecision']);$group_id=$group['id'];$group_ids[$group_id]=true;
                if($group['created']){$this->repository->add_history($group_id,'fulfillment-group:'.$group_id.':created','status_changed','Utworzono wewnętrzną grupę realizacji.',['toStatus'=>'pending','context'=>['assignment'=>$assignment['reason']]]);}
                $product=$item->get_product();$sku=$product instanceof \WC_Product?(string)$product->get_sku():'';
                $snapshot=['orderId'=>$order_id,'orderItemId'=>(int)$item_id,'productId'=>$product_id,'variationId'=>$variation_id,'productName'=>(string)$item->get_name(),'sku'=>$sku,'quantity'=>(float)$item->get_quantity(),'subtotal'=>(string)$item->get_subtotal(),'subtotalTax'=>(string)$item->get_subtotal_tax(),'total'=>(string)$item->get_total(),'totalTax'=>(string)$item->get_total_tax(),'currency'=>(string)$order->get_currency(),'supplierId'=>$assignment['supplierId'],'productLinkId'=>$assignment['productLinkId'],'supplierExternalId'=>$selected_link['external_id']??null,'assignmentReason'=>$assignment['reason'],'capturedAt'=>gmdate('c')];
                $inserted=$this->repository->add_item($group_id,['orderId'=>$order_id,'orderItemId'=>(int)$item_id,'productId'=>$product_id,'variationId'=>$variation_id,'supplierId'=>$assignment['supplierId'],'productLinkId'=>$assignment['productLinkId'],'quantity'=>(float)$item->get_quantity(),'assignmentReason'=>$assignment['reason'],'snapshot'=>$snapshot]);if($inserted)++$created;
                if($assignment['requiresManualDecision']&&$inserted){++$manual;$event_key='fulfillment-item:'.$item_id.':assignment-required';$this->repository->add_history($group_id,$event_key,'assignment_required','Pozycja wymaga ręcznego przypisania dostawcy.',['errorCode'=>$assignment['reason'],'context'=>['orderItemId'=>(int)$item_id,'productId'=>$product_id,'variationId'=>$variation_id]]);$this->repository->note_assignment_error($group_id,$assignment['reason']);}
        }
        foreach(array_keys($group_ids)as$group_id)$this->repository->refresh_group((int)$group_id);
        return['orderId'=>$order_id,'groups'=>count($group_ids),'itemsCreated'=>$created,'manualItems'=>$manual];
    }

    private function supplier_metadata(int$variation_id,int$product_id):array{
        foreach(array_filter([$variation_id,$product_id])as$id){$supplier_id=(int)get_post_meta($id,'_pupilovo_supplier_id',true);if($supplier_id>0)return['supplierId'=>$supplier_id,'externalId'=>trim((string)get_post_meta($id,'_pupilovo_supplier_external_id',true))];}
        return['supplierId'=>null,'externalId'=>null];
    }
}
