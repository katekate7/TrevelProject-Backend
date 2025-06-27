<?php
// src/Entity/Trip.php

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TripRepository::class)]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['trip:list','trip:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['trip:list','trip:read'])]
    private string $city;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['trip:list','trip:read'])]
    private string $country;

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['trip:list','trip:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['trip:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['trip:read'])]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['trip:read'])]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['trip:read'])]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['trip:read'])]          // <= expose only in a single-trip response
    private ?string $sightseeings = null;   //  NEW FIELD

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\OneToOne(mappedBy: 'trip', targetEntity: Weather::class, cascade: ['persist','remove'])]
    #[Groups(['trip:read'])]
    private ?Weather $weather = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /* getters & setters ================================================== */

    public function getId(): ?int { return $this->id; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $c): static { $this->city = $c; return $this; }

    public function getCountry(): string { return $this->country; }
    public function setCountry(string $c): static { $this->country = $c; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $u): static { $this->imageUrl = $u; return $this; }

    public function getStartDate(): ?\DateTimeImmutable { return $this->startDate; }
    public function setStartDate(?\DateTimeImmutable $d): static { $this->startDate = $d; return $this; }

    public function getEndDate(): ?\DateTimeImmutable { return $this->endDate; }
    public function setEndDate(?\DateTimeImmutable $d): static { $this->endDate = $d; return $this; }

    public function getSightseeings(): ?string { return $this->sightseeings; }
    public function setSightseeings(?string $s): static { $this->sightseeings = $s; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): static { $this->user = $u; return $this; }

    public function getWeather(): ?Weather { return $this->weather; }
    public function setWeather(?Weather $w): static { $this->weather = $w; return $this; }
}