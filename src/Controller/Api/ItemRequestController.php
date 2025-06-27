<?php
// src/Controller/Api/ItemRequestController.php
namespace App\Controller\Api;

use App\Entity\ItemRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/item-requests', name: 'api_item_requests_')]
class ItemRequestController extends AbstractController
{
    /* ── USER submits a request ───────────────────────────── */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $req, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($req->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'Missing name'], 400);
        }

        $ir = (new ItemRequest())
            ->setName($data['name'])
            ->setUser($this->getUser());            // 🔐 ties to current user

        $em->persist($ir);
        $em->flush();

        return $this->json(['message' => 'Request submitted'], 201);
    }

    /* ── ADMIN: list pending requests ─────────────────────── */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $list = $em->getRepository(ItemRequest::class)
                   ->findBy(['status' => 'pending'], ['id' => 'ASC']);

        $out = \array_map(fn(ItemRequest $r) => [
            'id'     => $r->getId(),
            'name'   => $r->getName(),
            'user'   => $r->getUser()?->getEmail(),
            'status' => $r->getStatus(),
        ], $list);

        return $this->json($out, 200);
    }

    /* ── ADMIN: approve / reject ──────────────────────────── */
    #[Route('/{id}', name: 'review', methods: ['PATCH'])]
    public function review(
        ItemRequest $reqEntity,
        Request $req,
        EntityManagerInterface $em
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($req->getContent(), true);
        if (!\in_array($data['action'] ?? '', ['approve', 'reject'], true)) {
            return $this->json(['error' => 'action must be approve|reject'], 400);
        }

        $reqEntity->setStatus($data['action'] === 'approve' ? 'approved' : 'rejected');
        $em->flush();

        return $this->json(['message' => 'Updated'], 200);
    }
}
