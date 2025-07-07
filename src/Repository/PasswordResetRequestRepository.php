<?php

namespace App\Repository;

use App\Entity\PasswordResetRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetRequest>
 */
class PasswordResetRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetRequest::class);
    }

    /**
     * Remove expired tokens (older than 24 hours)
     */
    public function removeExpiredTokens(): int
    {
        $oneDayAgo = new \DateTimeImmutable('-1 day');
        
        $qb = $this->createQueryBuilder('prr')
            ->delete()
            ->where('prr.createdAt < :oneDayAgo')
            ->setParameter('oneDayAgo', $oneDayAgo);

        return $qb->getQuery()->execute();
    }

    /**
     * Find valid token (not expired)
     */
    public function findValidToken(string $token): ?PasswordResetRequest
    {
        $oneDayAgo = new \DateTimeImmutable('-1 day');
        
        return $this->createQueryBuilder('prr')
            ->where('prr.token = :token')
            ->andWhere('prr.createdAt >= :oneDayAgo')
            ->setParameter('token', $token)
            ->setParameter('oneDayAgo', $oneDayAgo)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
