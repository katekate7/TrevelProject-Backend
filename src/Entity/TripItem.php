<?php

namespace App\Entity;

use App\Repository\TripItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripItemRepository::class)]
class TripItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class, inversedBy: 'tripItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Trip $trip = null;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'tripItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Item $item = null;

    #[ORM\Column(type: 'boolean', options: ["default" => false])]
    private bool $isChecked = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): static
    {
        $this->trip = $trip;
        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;
        return $this;
    }

    public function isChecked(): bool
    {
        return $this->isChecked;
    }

    public function setChecked(bool $isChecked): static
    {
        $this->isChecked = $isChecked;
        return $this;
    }
}
