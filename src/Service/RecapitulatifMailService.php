<?php
namespace App\Service;

use App\Entity\UserManagement\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class RecapitulatifMailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment     $twig,
        private string          $mailerFrom,
        private string          $appName = 'AgriLink'
    ) {}

    /**
     * @param array<int, \App\Entity\Exploitation\Exploitation> $exploitations
     */
    public function envoyer(User $user, array $exploitations): void
    {
        if (empty($exploitations)) return;

        $data        = $this->calculerStats($exploitations);
        $aujourd_hui = new \DateTime();

        $templateData = array_merge($data, [
            'user'           => $user,
            'appName'        => $this->appName,
            'today'          => $aujourd_hui,
            'exploitations'  => $exploitations,
        ]);

        // ── Email HTML ────────────────────────────────────────
        $htmlEmail = $this->twig->render(
            'exploitation/emails/recapitulatif.html.twig',
            $templateData
        );

        // ── PDF ───────────────────────────────────────────────
        $htmlPdf = $this->twig->render(
            'exploitation/emails/recapitulatif_pdf.html.twig',
            $templateData
        );

        $pdfContent = $this->genererPdf($htmlPdf);

        // ── Envoi email ───────────────────────────────────────
        $sujet = sprintf(
            '[%s] Votre récapitulatif agricole — %s',
            $this->appName,
            $aujourd_hui->format('d/m/Y')
        );

        $nomFichier = sprintf(
            'recapitulatif-agrilink-%s.pdf',
            $aujourd_hui->format('Y-m-d')
        );

        $email = (new Email())
            ->from(new Address($this->mailerFrom, $this->appName))
            ->to(new Address(
                (string) $user->getEmail(),
                $user->getDisplayName()
            ))
            ->subject($sujet)
            ->html($htmlEmail)
            ->attach($pdfContent, $nomFichier, 'application/pdf');

        $this->mailer->send($email);
    }

    // ── Générer PDF ───────────────────────────────────────────

    private function genererPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    // ── Calculer statistiques ─────────────────────────────────

    /**
     * @param array<int, \App\Entity\Exploitation\Exploitation> $exploitations
     * @return array<string, mixed>
     */
    private function calculerStats(array $exploitations): array
    {
        $totalParcelles     = 0;
        $totalCultures      = 0;
        $totalSuperficie    = 0.0;
        $culturesEnCours    = [];
        $prochainesRecoltes = [];
        $cultureCounts      = [];
        $aujourd_hui        = new \DateTimeImmutable();
        $dans30jours        = $aujourd_hui->modify('+30 days');

        foreach ($exploitations as $exploitation) {
            $desc = $exploitation->getDescription() ?? '';
            if (str_starts_with($desc, '[ATTENTE]')
                || str_starts_with($desc, '[REJETE]')) {
                continue;
            }

            foreach ($exploitation->getParcelles() as $parcelle) {
                if (str_starts_with((string) $parcelle->getNom(), '[ATTENTE]')
                    || str_starts_with((string) $parcelle->getNom(), '[REJETE]')) {
                    continue;
                }

                $totalParcelles++;
                $totalSuperficie +=
                    (float) ($parcelle->getSuperficie() ?? 0);

                foreach ($parcelle->getCultures() as $culture) {
                    $statutBase = explode(
                        '|',
                        $culture->getStatut() ?? ''
                    )[0];

                    if ($statutBase === 'EN_ATTENTE') continue;

                    $totalCultures++;

                    $type = $culture->getType() ?? 'AUTRE';
                    $cultureCounts[$type] =
                        ($cultureCounts[$type] ?? 0) + 1;

                    if (in_array($statutBase, [
                        'EN_CROISSANCE',
                        'SEMEE',
                        'EN_RECOLTE',
                    ])) {
                        $culturesEnCours[] = [
                            'culture'      => $culture,
                            'parcelle'     => $parcelle,
                            'exploitation' => $exploitation,
                            'statut'       => $statutBase,
                        ];
                    }

                    $dateRecolte = $culture->getDateRecolte();
                    if ($dateRecolte
                        && $statutBase !== 'RECOLTEE'
                        && $statutBase !== 'ABANDONNEE'
                    ) {
                        if ($dateRecolte instanceof \DateTime) {
                            $dateRecolte =
                                \DateTimeImmutable::createFromMutable(
                                    $dateRecolte
                                );
                        }

                        if ($dateRecolte >= $aujourd_hui
                            && $dateRecolte <= $dans30jours
                        ) {
                            $jours = (int) $aujourd_hui
                                ->diff($dateRecolte)->days;

                            $prochainesRecoltes[] = [
                                'culture'      => $culture,
                                'parcelle'     => $parcelle,
                                'exploitation' => $exploitation,
                                'jours'        => $jours,
                                'date'         => $dateRecolte,
                            ];
                        }
                    }
                }
            }
        }

        usort(
            $prochainesRecoltes,
            fn($a, $b) => $a['jours'] <=> $b['jours']
        );

        // Stats du mois
        $debutMois = $aujourd_hui
            ->modify('first day of this month')
            ->setTime(0, 0, 0);
        $finMois = $aujourd_hui
            ->modify('last day of this month')
            ->setTime(23, 59, 59);

        $recolteesCeMois = 0;
        foreach ($exploitations as $exploitation) {
            foreach ($exploitation->getParcelles() as $parcelle) {
                foreach ($parcelle->getCultures() as $culture) {
                    $dr = $culture->getDateRecolte();
                    if (!$dr) continue;
                    if ($dr instanceof \DateTime) {
                        $dr = \DateTimeImmutable::createFromMutable($dr);
                    }
                    if ($dr >= $debutMois && $dr <= $finMois) {
                        $recolteesCeMois++;
                    }
                }
            }
        }

        return [
            'totalParcelles'     => $totalParcelles,
            'totalCultures'      => $totalCultures,
            'totalSuperficie'    => $totalSuperficie,
            'culturesEnCours'    => $culturesEnCours,
            'prochainesRecoltes' => $prochainesRecoltes,
            'cultureCounts'      => $cultureCounts,
            'recolteesCeMois'    => $recolteesCeMois,
        ];
    }
}