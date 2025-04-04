<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'get_current_user', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
    
        return $this->json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(), // твоя одна роль (user/admin)
            'roles' => $user->getRoles(), // Symfony-масив ролей
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }
    
}

