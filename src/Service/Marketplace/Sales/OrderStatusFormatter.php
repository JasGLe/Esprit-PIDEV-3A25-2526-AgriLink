<?php

namespace App\Service\Marketplace\Sales;

use App\Entity\Marketplace\Commandes;

final class OrderStatusFormatter
{
    public function formatOrderRef(Commandes $commande): string
    {
        $id = $commande->getId();
        if ($id > 0) {
            return '#CMD'.str_pad((string) $id, 3, '0', STR_PAD_LEFT);
        }

        return (string) $commande->getNumCommande();
    }

    public function humanizeStatus(string $status): string
    {
        $raw = trim($status);
        if ($raw === '') {
            return '-';
        }

        $normalized = str_replace('_', ' ', strtolower($raw));
        return ucfirst($normalized);
    }
}

