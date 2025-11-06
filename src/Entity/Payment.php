<?php
namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)] #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column(length: 64)] private string $paypalOrderId;
    #[ORM\Column(length: 64, nullable: true)] private ?string $paypalCaptureId = null;
    #[ORM\Column(length: 24)] private string $status; // CREATED|COMPLETED|CANCELED|FAILED
    #[ORM\Column] private int $amountCents;
    #[ORM\Column(length: 8)] private string $currency = 'EUR';
    #[ORM\Column(type: 'text', nullable: true)] private ?string $ordersCsv = null; // comma-separated order IDs
    #[ORM\Column(type: 'text', nullable: true)] private ?string $rawPayload = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function getId(): ?int { return $this->id; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(Client $c): self { $this->client = $c; return $this; }
    public function getPaypalOrderId(): string { return $this->paypalOrderId; }
    public function setPaypalOrderId(string $v): self { $this->paypalOrderId = $v; return $this; }
    public function getPaypalCaptureId(): ?string { return $this->paypalCaptureId; }
    public function setPaypalCaptureId(?string $v): self { $this->paypalCaptureId = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): self { $this->status = $v; return $this; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function setAmountCents(int $v): self { $this->amountCents = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): self { $this->currency = $v; return $this; }
    public function getOrdersCsv(): ?string { return $this->ordersCsv; }
    public function setOrdersCsv(?string $v): self { $this->ordersCsv = $v; return $this; }
    public function getRawPayload(): ?string { return $this->rawPayload; }
    public function setRawPayload(?string $v): self { $this->rawPayload = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
