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
    #[Route('/', name: 'get_users', methods: ['GET'])]
    public function getUsers(UserRepository $userRepository): JsonResponse
    {
        return $this->json($userRepository->findAll());
    }

    #[Route('/me', name: 'get_current_user', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        $user = $this->getUser();
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRoles()
        ]);
    }

    #[Route('/register', name: 'register_user', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordEncoderInterface $passwordEncoder): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($passwordEncoder->encodePassword($user, $data['password']));
        $user->setRoles(['ROLE_USER']);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'User created successfully']);
    }
}
