<?php
namespace App\Service;

use App\Entity\Notifications;
use App\Entity\UserManagement\User;
use Doctrine\ORM\EntityManagerInterface;
class NotificationService
{
  public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function notifierAdmin(User $admin, string $type,
                                  string $titre, string $corps): void
    {
        $notif = new Notifications();
        $notif->setUser($admin);
        $notif->setType($type);
        $notif->setTitle($titre);
        $notif->setBody($corps);
        $notif->setCreatedAt(new \DateTime());

        $this->em->persist($notif);
        $this->em->flush();
    }

    public function notifierUser(User $user, string $type,
                                 string $titre, string $corps): void
    {
        $notif = new Notifications();
        $notif->setUser($user);
        $notif->setType($type);
        $notif->setTitle($titre);
        $notif->setBody($corps);
        $notif->setCreatedAt(new \DateTime());

        $this->em->persist($notif);
        $this->em->flush();
    }

    // ── Exploitation ──────────────────────────────────────

    public function notifierNouvelleExploitation(User $admin,
                                                 string $nomExp,
                                                 string $ownerEmail): void
    {
        $this->notifierAdmin(
            $admin,
            'moderation_attente', // ✅ string
            '🏡 Nouvelle exploitation à valider',
            "L'agriculteur $ownerEmail a soumis l'exploitation « $nomExp » — en attente de votre validation."
        );
    }

    public function notifierExploitationApprouvee(User $agriculteur,
                                                  string $nomExp): void
    {
        $this->notifierUser(
            $agriculteur,
            'moderation_approuve', // ✅ string
            '✅ Exploitation approuvée',
            "Votre exploitation « $nomExp » a été approuvée."
        );
    }

    public function notifierExploitationRejetee(User $agriculteur,
                                                string $nomExp,
                                                string $raison = ''): void
    {
        $corps = "Votre exploitation « $nomExp » a été rejetée.";
        if ($raison) $corps .= " Raison : $raison";

        $this->notifierUser(
            $agriculteur,
            'moderation_rejete', // ✅ string
            '❌ Exploitation rejetée',
            $corps
        );
    }

    // ── Parcelle ──────────────────────────────────────────

    public function notifierNouvelleParcelle(User $admin,
                                             string $nomParc,
                                             string $ownerEmail): void
    {
        $this->notifierAdmin(
            $admin,
            'moderation_attente',
            '🌿 Nouvelle parcelle à valider',
            "L'agriculteur $ownerEmail a soumis la parcelle « $nomParc »."
        );
    }

    public function notifierParcelleApprouvee(User $agriculteur,
                                              string $nomParc): void
    {
        $this->notifierUser(
            $agriculteur,
            'moderation_approuve',
            '✅ Parcelle approuvée',
            "Votre parcelle « $nomParc » a été approuvée."
        );
    }

    public function notifierParcelleRejetee(User $agriculteur,
                                            string $nomParc): void
    {
        $this->notifierUser(
            $agriculteur,
            'moderation_rejete',
            '❌ Parcelle rejetée',
            "Votre parcelle « $nomParc » a été rejetée."
        );
    }

    // ── Culture ───────────────────────────────────────────

    public function notifierNouvelleCulture(User $admin,
                                            string $nomCulture,
                                            string $ownerEmail): void
    {
        $this->notifierAdmin(
            $admin,
            'moderation_attente',
            '🌱 Nouvelle culture à valider',
            "L'agriculteur $ownerEmail a soumis la culture « $nomCulture »."
        );
    }

    public function notifierCultureApprouvee(User $agriculteur,
                                             string $nomCulture): void
    {
        $this->notifierUser(
            $agriculteur,
            'moderation_approuve',
            '✅ Culture approuvée',
            "Votre culture « $nomCulture » a été approuvée."
        );
    }

    public function notifierCultureRejetee(User $agriculteur,
                                           string $nomCulture): void
    {
        $this->notifierUser(
            $agriculteur,
            'moderation_rejete',
            '❌ Culture rejetée',
            "Votre culture « $nomCulture » a été rejetée."
        );
    }
}