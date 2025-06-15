<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/users')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'get_current_user', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'        => $user->getId(),
            'username'  => $user->getUsername(),
            'email'     => $user->getEmail(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/register', name: 'register_user', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Invalid input'], 400);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Email already used'], 409);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        // однорольова модель: встановлюємо єдину роль
        $user->setRole('ROLE_USER');

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        return $this->json(['message' => 'User successfully registered! ✅'], 201);
    }

    #[Route('/create-admin', name: 'create_admin_user', methods: ['POST'])]
    public function createAdmin(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Invalid input'], 400);
        }

        if ($em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'User with this email already exists'], 409);
        }

        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        // теж однорольова модель
        $user->setRole('ROLE_ADMIN');

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $em->persist($user);
        $em->flush();

        return $this->json([
            'message' => 'Admin user created ✅',
            'admin'   => [
                'id'       => $user->getId(),
                'username' => $user->getUsername(),
                'email'    => $user->getEmail(),
            ],
        ], 201);
    }
}
