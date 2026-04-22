<?php

namespace App\Entity\Forum;

use App\Entity\UserManagement\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\Forum\MessageRepository::class)]
#[ORM\Table(name: 'message')]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le forum associé est obligatoire.')]
    private Forum $forum;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'idUtilisateur', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Le message ne doit pas depasser {{ limit }} caracteres.'
    )]
    private string $contenu;

    #[ORM\Column(name: 'audio_path', type: Types::STRING, length: 255, nullable: true)]
    private ?string $audioPath = null;

    #[ORM\Column(name: 'date_envoi', type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateEnvoi;

    public function __construct()
    {
        $this->dateEnvoi = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getForum(): Forum
    {
        return $this->forum;
    }

    public function setForum(Forum $forum): static
    {
        $this->forum = $forum;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu ?? '';
        return $this;
    }

    public function getAudioPath(): ?string
    {
        return $this->audioPath;
    }

    public function setAudioPath(?string $audioPath): static
    {
        $this->audioPath = $audioPath;
        return $this;
    }

    public function getDateEnvoi(): \DateTimeInterface
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(\DateTimeInterface $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }
}
