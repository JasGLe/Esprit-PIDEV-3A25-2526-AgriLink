<?php

namespace App\Entity\Marketplace;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\Marketplace\MarketplaceUserEventRepository::class)]
#[ORM\Table(name: 'marketplace_user_event')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_mue_user_created')]
#[ORM\Index(columns: ['event_type', 'created_at'], name: 'idx_mue_type_created')]
#[ORM\Index(columns: ['product_id', 'created_at'], name: 'idx_mue_product_created')]
class MarketplaceUserEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(name: 'event_type', type: Types::STRING, length: 30)]
    private string $eventType;

    #[ORM\Column(name: 'product_id', type: Types::INTEGER, nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(name: 'query_text', type: Types::STRING, length: 255, nullable: true)]
    private ?string $queryText = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'meta_json', type: Types::JSON, nullable: true)]
    private ?array $metaJson = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId !== null && $userId > 0 ? $userId : null;
        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = strtolower(trim($eventType));
        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): static
    {
        $this->productId = $productId !== null && $productId > 0 ? $productId : null;
        return $this;
    }

    public function getQueryText(): ?string
    {
        return $this->queryText;
    }

    public function setQueryText(?string $queryText): static
    {
        $queryText = $queryText !== null ? trim($queryText) : null;
        $this->queryText = $queryText !== '' ? $queryText : null;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getMetaJson(): ?array
    {
        return $this->metaJson;
    }

    public function setMetaJson(?array $metaJson): static
    {
        $this->metaJson = $metaJson;
        return $this;
    }
}

