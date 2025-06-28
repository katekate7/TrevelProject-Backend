<?php
// src/Entity/Trajet.php

namespace App\Entity;

use App\Repository\TrajetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrajetRepository::class)]
class Trajet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class, inversedBy:"trajets")]
    #[ORM\JoinColumn(nullable:false, onDelete:"CASCADE")]
    private ?Trip $trip = null;

    #[ORM\Column(type:"json")]
    private array $routeData = [];

    #[ORM\Column(type:"float", nullable:true)]
    private ?float $distance = null;

    #[ORM\Column(type:"datetime_immutable")]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTrip(): ?Trip { return $this->trip; }
    public function setTrip(Trip $trip): self { $this->trip = $trip; return $this; }

    public function getRouteData(): array { return $this->routeData; }
    public function setRouteData(array $d): self { $this->routeData = $d; return $this; }

    public function getDistance(): ?float { return $this->distance; }
    public function setDistance(?float $d): self { $this->distance = $d; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
