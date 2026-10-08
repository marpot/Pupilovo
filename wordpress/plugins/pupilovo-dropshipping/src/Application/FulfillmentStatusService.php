<?php

namespace Pupilovo\SupplierHub\Application;

use Pupilovo\SupplierHub\Domain\Fulfillment\FulfillmentStatus;
use Pupilovo\SupplierHub\Infrastructure\Repository\FulfillmentRepository;

defined('ABSPATH') || exit;

final class FulfillmentStatusService {
    private const TRANSITIONS = [
        'pending' => ['ready','manually_approved','failed','cancelled'],
        'ready' => ['manually_approved','sent','failed','cancelled'],
        'manually_approved' => ['ready','sent','failed','cancelled'],
        'sent' => ['acknowledged','failed','cancelled'],
        'acknowledged' => ['shipped','failed','cancelled'],
        'shipped' => ['delivered','failed'],
        'failed' => ['pending','ready','manually_approved','cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function __construct(private readonly FulfillmentRepository $repository = new FulfillmentRepository()) {}

    public function transition(int$group_id,string$next,int$actor_user_id=0,?string$error_code=null,string$message=''):array{
        if(!FulfillmentStatus::is_valid($next))throw new \InvalidArgumentException('Nieprawidłowy status realizacji dostawcy.');
        $group=$this->repository->find_group($group_id);if(!$group)throw new \InvalidArgumentException('Nie znaleziono grupy realizacji.');$current=$group['status'];if($current===$next)return$group;
        if(!in_array($next,self::TRANSITIONS[$current]??[],true))throw new \DomainException('Niedozwolona zmiana statusu realizacji.');
        global$wpdb;$wpdb->query('START TRANSACTION');
        try{if(!$this->repository->update_status($group_id,$current,$next,$error_code))throw new \RuntimeException('Status realizacji zmienił się równolegle. Spróbuj ponownie.');$this->repository->add_history($group_id,'status:'.wp_generate_uuid4(),'status_changed',$message!==''?$message:'Zmieniono status realizacji dostawcy.',['fromStatus'=>$current,'toStatus'=>$next,'errorCode'=>$error_code,'actorUserId'=>$actor_user_id]);$wpdb->query('COMMIT');}
        catch (\Throwable $error){$wpdb->query('ROLLBACK');throw $error;}
        return$this->repository->find_group($group_id)??[];
    }
}
