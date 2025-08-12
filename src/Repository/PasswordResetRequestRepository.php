<?php
/**
 * Password Reset Request Repository
 * 
 * This repository handles database operations for password reset requests,
 * including finding valid tokens and cleaning up expired ones.
 * 
 * @package App\Repository
 * @author Travel Project Team
 */

namespace App\Repository;

use App\Entity\PasswordResetRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository for managing password reset requests in the database
 * 
 * This repository provides methods for working with password reset tokens,
 * including validation and cleanup of expired tokens.
 * 
 * @extends ServiceEntityRepository<PasswordResetRequest>
 */
class PasswordResetRequestRepository extends ServiceEntityRepository
{
    /**
     * Constructor to initialize the repository with its entity class
     * 
     * @param ManagerRegistry $registry The Doctrine registry service
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetRequest::class);
    }

    /**
     * Remove expired tokens (older than 24 hours)
     * 
     * This method performs database cleanup by removing all password reset
     * requests that have expired (older than 24 hours). This helps maintain
     * database efficiency and improves security by removing unused tokens.
     * 
     * @return int The number of expired tokens that were removed
     */
    public function removeExpiredTokens(): int
    {
        // Calculate the timestamp for 24 hours ago
        $oneDayAgo = new \DateTimeImmutable('-1 day');
        
        // Create query to delete all tokens created before the cutoff time
        $qb = $this->createQueryBuilder('prr')
            ->delete()
            ->where('prr.createdAt < :oneDayAgo')
            ->setParameter('oneDayAgo', $oneDayAgo);

        // Execute the deletion and return the count of deleted records
        return $qb->getQuery()->execute();
    }

    /**
     * Find a valid token that hasn't expired
     * 
     * This method retrieves a password reset request by its token string,
     * ensuring that the token is still valid (not older than 24 hours).
     * If the token is expired or doesn't exist, it returns null.
     * 
     * @param string $token The token string to search for
     * @return PasswordResetRequest|null The password reset request entity or null if not found/expired
     */
    public function findValidToken(string $token): ?PasswordResetRequest
    {
        // Calculate the timestamp for 24 hours ago
        $oneDayAgo = new \DateTimeImmutable('-1 day');
        
        // Query for a token that matches and is not expired
        return $this->createQueryBuilder('prr')
            ->where('prr.token = :token')
            ->andWhere('prr.createdAt >= :oneDayAgo')
            ->setParameter('token', $token)
            ->setParameter('oneDayAgo', $oneDayAgo)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
