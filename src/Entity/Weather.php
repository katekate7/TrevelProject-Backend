<?php

namespace App\Entity;

use App\Repository\WeatherRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeatherRepository::class)]
class Weather
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Trip::class, inversedBy: 'weather', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Trip $trip = null;

    #[ORM\Column(type: 'float')]
    private float $temperature;

    #[ORM\Column(type: 'integer')]
    private int $humidity;

    #[ORM\Column(type: 'string', length: 255)]
    private string $weatherDescription;

    #[ORM\Column(type: 'datetime_immutable', options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getHumidity(): int
    {
        return $this->humidity;
    }

    public function setHumidity(int $humidity): static
    {
        $this->humidity = $humidity;
        return $this;
    }

    public function getWeatherDescription(): string
    {
        return $this->weatherDescription;
    }

    public function setWeatherDescription(string $weatherDescription): static
    {
        $this->weatherDescription = $weatherDescription;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
