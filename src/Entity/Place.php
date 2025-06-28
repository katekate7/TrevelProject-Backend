<?php
// src/Entity/Place.php

namespace App\Entity;

use App\Repository\PlaceRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
class Place
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type:"integer")]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class, inversedBy:"places")]
    #[ORM\JoinColumn(nullable:false, onDelete:"CASCADE")]
    private ?Trip $trip = null;

    #[ORM\Column(type:"string", length:255)]
    private string $name;

    #[ORM\Column(type:"float")]
    private float $lat;

    #[ORM\Column(type:"float")]
    private float $lng;

    public function getId(): ?int { return $this->id; }

    public function getTrip(): ?Trip { return $this->trip; }
    public function setTrip(Trip $trip): self { $this->trip = $trip; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getLat(): float { return $this->lat; }
    public function setLat(float $lat): self { $this->lat = $lat; return $this; }

    public function getLng(): float { return $this->lng; }
    public function setLng(float $lng): self { $this->lng = $lng; return $this; }
}
