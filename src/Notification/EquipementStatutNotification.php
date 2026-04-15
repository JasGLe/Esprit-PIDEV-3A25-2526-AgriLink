<?php

namespace App\Notification;

use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Notification\SmsNotificationInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

class EquipementStatutNotification extends Notification implements SmsNotificationInterface
{
    public function __construct(
        private string $nomEquipement,
        private string $ancienStatut,
        private string $nouveauStatut
    ) {
        parent::__construct();
    }

    public function asSmsMessage(SmsRecipientInterface $recipient, string $transport = null): ?SmsMessage
    {
        $texte = sprintf(
            'AgriLink : Votre équipement "%s" a changé de statut : %s → %s. Connectez-vous pour plus de détails.',
            $this->nomEquipement,
            $this->ancienStatut,
            $this->nouveauStatut
        );

        return new SmsMessage($recipient->getPhone(), $texte);
    }

    public function getChannels(RecipientInterface $recipient): array
    {
        return ['sms/twilio'];
    }
}
