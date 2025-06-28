<?php
// src/Controller/Api/UserController.php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\PasswordResetRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerInterface $mailer,
    ) {}

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->json([
            'id'        => $user->getId(),
            'username'  => $user->getUsername(),
            'email'     => $user->getEmail(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i'),
        ]);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $req): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?: [];
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Email in use'], 409);
        }

        $user = (new User())
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setRole('user');
        $user->setPassword(
            $this->hasher->hashPassword($user, $data['password'])
        );

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['message' => 'Registered'], 201);
    }

    #[Route('/create-admin', name: 'create_admin', methods: ['POST'])]
    public function createAdmin(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $data = json_decode($req->getContent(), true) ?: [];
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'Missing fields'], 400);
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return $this->json(['error' => 'Email in use'], 409);
        }

        $user = (new User())
            ->setUsername($data['username'])
            ->setEmail($data['email'])
            ->setRole('admin');
        $user->setPassword(
            $this->hasher->hashPassword($user, $data['password'])
        );

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['message' => 'Admin created'], 201);
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $users = $this->em->getRepository(User::class)->findAll();
        $data = array_map(fn(User $u) => [
            'id'        => $u->getId(),
            'username'  => $u->getUsername(),
            'email'     => $u->getEmail(),
            'role'     => $u->getRole(),
            'createdAt' => $u->getCreatedAt()->format('Y-m-d'),
        ], $users);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $u = $this->em->find(User::class, $id) ?? null;
        if (!$u) return $this->json(['error'=>'Not found'], 404);

        $d = json_decode($req->getContent(), true) ?? [];
        if (isset($d['username'])) $u->setUsername($d['username']);
        if (isset($d['email']))    $u->setEmail($d['email']);
        if (isset($d['role']) && \in_array($d['role'], ['user','admin'], true)) {
            $u->setRole($d['role']);
        }
        $this->em->flush();

        return $this->json(['saved'=>true]);
    }


    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }
        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'User deleted']);
    }

    #[Route('/{id}/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        /** @var User|null $user */
        $user = $this->em->find(User::class, $id);
        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        // Створюємо запит на відновлення пароля
        $pr = new PasswordResetRequest($user);
        $this->em->persist($pr);
        $this->em->flush();

        // Відправка листа
        $resetLink = sprintf(
            '%s/reset-password/%s',
            $_ENV['APP_FRONTEND_URL'] ?? 'http://localhost:5173',
            $pr->getToken()
        );

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@yourapp.com', 'Your App'))
            ->to($user->getEmail())
            ->subject('Відновлення пароля')
            ->htmlTemplate('emails/reset_password.html.twig')
            ->context([
                'username'  => $user->getUsername(),
                'resetLink' => $resetLink,
            ]);

        $this->mailer->send($email);

        return $this->json(['message' => 'Лист із лінком надіслано']);
    }
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $d = json_decode($req->getContent(), true) ?? [];
        foreach (['username', 'email', 'password'] as $f) {
            if (empty($d[$f])) {
                return $this->json(["error"=>"Missing $f"], 400);
            }
        }
        if ($this->em->getRepository(User::class)
                     ->findOneBy(['email'=>$d['email']])) {
            return $this->json(['error'=>'Email in use'], 409);
        }

        $role = ($d['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

        $u = (new User())
            ->setUsername($d['username'])
            ->setEmail($d['email'])
            ->setRole($role);
        $u->setPassword($this->hasher->hashPassword($u, $d['password']));

        $this->em->persist($u);
        $this->em->flush();

        return $this->json(['id'=>$u->getId(), 'role'=>$u->getRole()], 201);
    }


}
