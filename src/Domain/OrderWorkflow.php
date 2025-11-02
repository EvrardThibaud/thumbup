<?php
namespace App\Domain;

use App\Entity\Order;
use App\Enum\OrderStatus;

final class OrderWorkflow
{
    public function canAccept(Order $o): bool   { return $o->getStatus() === OrderStatus::CREATED; }
    public function canRefuse(Order $o): bool   { return $o->getStatus() === OrderStatus::CREATED; }
    public function canStart(Order $o): bool    { return $o->getStatus() === OrderStatus::ACCEPTED; }
    public function canDeliver(Order $o): bool  { return $o->getStatus() === OrderStatus::DOING; }
    public function canCancel(Order $o): bool   { return $o->getStatus() === OrderStatus::CREATED; }
    public function canFinish(Order $o): bool           { return $o->getStatus() === OrderStatus::DELIVERED; }
    public function canRequestRevision(Order $o): bool  { return $o->getStatus() === OrderStatus::DELIVERED; }

    public function accept(Order $o): void   { if (!$this->canAccept($o))        throw new \LogicException('Invalid transition'); $o->setStatus(OrderStatus::ACCEPTED);  $o->setUpdatedAt(new \DateTimeImmutable()); }
    public function refuse(Order $o): void   { if (!$this->canRefuse($o))        throw new \LogicException('Invalid transition'); $o->setStatus(OrderStatus::REFUSED);   $o->setUpdatedAt(new \DateTimeImmutable()); }
    public function start(Order $o): void    { if (!$this->canStart($o))         throw new \LogicException('Invalid transition'); $o->setStatus(OrderStatus::DOING);     $o->setUpdatedAt(new \DateTimeImmutable()); }
    public function deliver(Order $o): void  { if (!$this->canDeliver($o))       throw new \LogicException('Invalid transition'); $o->setStatus(OrderStatus::DELIVERED); $o->setUpdatedAt(new \DateTimeImmutable()); }
    public function cancel(Order $o): void   { if (!$this->canCancel($o))        throw new \LogicException('Invalid transition'); $o->setStatus(OrderStatus::CANCELED);  $o->setUpdatedAt(new \DateTimeImmutable()); }
    public function finish(Order $o): void   { if (!$this->canFinish($o))        throw new \LogicException('Invalid transition'); $o->setStatus(OrderStatus::FINISHED);  $o->setUpdatedAt(new \DateTimeImmutable()); }
    public function requestRevision(Order $o): void
    {
        if (!$this->canRequestRevision($o)) throw new \LogicException('Invalid transition');
        $o->setStatus(OrderStatus::REVISION);
        $o->setUpdatedAt(new \DateTimeImmutable());
    }
}
