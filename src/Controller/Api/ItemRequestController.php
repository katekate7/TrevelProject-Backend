<?php

namespace App\Controller\Api;

use App\Entity\Item;
use App\Entity\ItemRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/item-requests', name: 'api_item_requests_')]
class ItemRequestController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /* USER — створити запит */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) return $this->json([], 401);

        $data = json_decode($request->getContent(), true) ?? [];
        if (empty($data['name'])) {
            return $this->json(['error' => 'Name is required'], 400);
        }

        $ir = (new ItemRequest())
            ->setName($data['name'])
            ->setUser($user);                 // status = 'pending' за замовч.

        $this->em->persist($ir);
        $this->em->flush();

        return $this->json([
            'message' => 'Request submitted',
            'request' => [
                'id'        => $ir->getId(),
                'name'      => $ir->getName(),
                'status'    => $ir->getStatus(),
            ],
        ], 201);
    }

    /* ADMIN — отримати список */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status   = $request->query->get('status');               // ?status=pending
        $criteria = $status ? ['status' => $status] : [];

        $reqs = $this->em->getRepository(ItemRequest::class)
                         ->findBy($criteria, ['id' => 'ASC']);

        return $this->json(array_map(fn(ItemRequest $r) => [
            'id'          => $r->getId(),
            'name'        => $r->getName(),
            'requestedBy' => $r->getUser()?->getEmail(),
            'status'      => $r->getStatus(),
        ], $reqs));
    }

    /* ADMIN — review (approve / reject) */
    #[Route('/{id}', name: 'review', methods: ['PATCH'])]
    public function review(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var ItemRequest|null $ir */
        $ir = $this->em->find(ItemRequest::class, $id);
        if (!$ir)                      return $this->json(['error'=>'Not found'], 404);
        if ($ir->getStatus() !== 'pending')
            return $this->json(['error'=>'Already reviewed'], 409);

        $data   = json_decode($request->getContent(), true) ?? [];
        $action = $data['action'] ?? null;
        if (!\in_array($action, ['approve','reject'], true)) {
            return $this->json(['error'=>'action must be approve|reject'], 400);
        }

        if ($action === 'approve') {
            $item = (new Item())
                ->setName($ir->getName());
                $this->em->persist($item);
            $ir->setStatus('approved');
        } else {
            $ir->setStatus('rejected');
        }

        $this->em->flush();
        return $this->json(['message' => "Request $action"]);
    }

    /* ADMIN — DELETE */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $ir = $this->em->find(ItemRequest::class, $id);
        if (!$ir) return $this->json(['error'=>'Not found'], 404);

        $this->em->remove($ir);
        $this->em->flush();
        return $this->json(['message'=>'Request deleted']);
    }
}
