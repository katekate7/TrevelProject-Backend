<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $username;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 20, options: ["default" => "user"])]
    private string $role = 'user';

    #[ORM\Column(type: 'datetime_immutable', options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Trip::class, cascade: ['persist', 'remove'])]
    private Collection $trips;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ItemRequest::class, cascade: ['persist', 'remove'])]
    private Collection $itemRequests;

    public function __construct()
    {
        $this->trips = new ArrayCollection();
        $this->itemRequests = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER']; // Базова роль кожного користувача
    
        if ($this->role === 'admin') {
            $roles[] = 'ROLE_ADMIN';
        }
    
        return array_unique($roles);
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTrips(): Collection
    {
        return $this->trips;
    }

    public function addTrip(Trip $trip): static
    {
        if (!$this->trips->contains($trip)) {
            $this->trips->add($trip);
            $trip->setUser($this);
        }
        return $this;
    }

    public function removeTrip(Trip $trip): static
    {
        if ($this->trips->removeElement($trip)) {
            if ($trip->getUser() === $this) {
                $trip->setUser(null);
            }
        }
        return $this;
    }

    public function getItemRequests(): Collection
    {
        return $this->itemRequests;
    }
    
    public function addItemRequest(ItemRequest $itemRequest): static
    {
        if (!$this->itemRequests->contains($itemRequest)) {
            $this->itemRequests->add($itemRequest);
            $itemRequest->setUser($this);
        }
        return $this;
    }
    
    public function removeItemRequest(ItemRequest $itemRequest): static
    {
        if ($this->itemRequests->removeElement($itemRequest)) {
            if ($itemRequest->getUser() === $this) {
                $itemRequest->setUser(null);
            }
        }
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // Очистка даних, якщо потрібно
    }
    
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
