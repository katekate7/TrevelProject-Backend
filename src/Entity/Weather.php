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
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Trip $trip = null;

    #[ORM\Column(type: 'float',   nullable: true)]
    private ?float $temperature = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int   $humidity = null;

    #[ORM\Column(type: 'string',  length: 255, nullable: true)]
    private ?string $weatherDescription = null;

    // remove or comment out if you no longer need the raw JSON
    // #[ORM\Column(type: 'json')]
    // private array $forecast = [];

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
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

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getHumidity(): ?int
    {
        return $this->humidity;
    }

    public function setHumidity(int $humidity): static
    {
        $this->humidity = $humidity;
        return $this;
    }

    public function getWeatherDescription(): ?string
    {
        return $this->weatherDescription;
    }

    public function setWeatherDescription(string $desc): static
    {
        $this->weatherDescription = $desc;
        return $this;
    }

    // if you kept the JSON forecast:
    // public function getForecast(): array
    // {
    //     return $this->forecast;
    // }
    //
    // public function setForecast(array $forecast): static
    // {
    //     $this->forecast = $forecast;
    //     return $this;
    // }

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
