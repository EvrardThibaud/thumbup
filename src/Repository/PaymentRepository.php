<?php
namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Payment::class); }

    public function findOneByPaypalOrderId(string $id): ?Payment
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paypalOrderId = :i')->setParameter('i',$id)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return Payment[] */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user','u')->addSelect('u')
            ->leftJoin('p.client','c')->addSelect('c')
            ->orderBy('p.createdAt','DESC')
            ->getQuery()->getResult();
    }
}
