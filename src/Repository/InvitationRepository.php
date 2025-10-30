<?php

namespace App\Repository;

use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findUsableByToken(string $token): ?Invitation
    {
        $inv = $this->findOneBy(['token' => $token]);
        if (!$inv) return null;
        return $inv->isUsable() ? $inv : null;
    }
}
