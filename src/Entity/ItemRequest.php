<?php

namespace App\Entity;               // ← ОБОВʼЯЗКОВО таке саме, як у всіх Entity

use App\Repository\ItemRequestRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemRequestRepository::class)]
class ItemRequest                       // ← пишемо ТАК САМО, як назва файлу
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'itemRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'pending'])]
    private string $status = 'pending';

    /* ───── getters / setters ───── */

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): static { $this->user = $u; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $s): static { $this->status = $s; return $this; }
}
