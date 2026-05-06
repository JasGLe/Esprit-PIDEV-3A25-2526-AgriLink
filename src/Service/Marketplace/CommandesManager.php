<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commandes;

final class CommandesManager
{
    public function validate(Commandes $commande): bool
    {
        if (trim($commande->getNumCommande()) === '') {
            throw new \InvalidArgumentException('Le numéro de commande est obligatoire');
        }

        if ($commande->getQuantite() <= 0) {
            throw new \InvalidArgumentException('La quantité totale doit être supérieure à zéro');
        }

        if ($commande->getPrixTotal() <= 0) {
            throw new \InvalidArgumentException('Le prix total doit être supérieur à zéro');
        }

        if (trim($commande->getStatus()) === '') {
            throw new \InvalidArgumentException('Le statut de la commande est obligatoire');
        }

        $email = $commande->getEmail();
        if ($email !== null && trim($email) !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        $promoDiscount = $commande->getPromoDiscountTotal();
        if ($promoDiscount !== null && $promoDiscount < 0) {
            throw new \InvalidArgumentException('La remise totale ne peut pas être négative');
        }

        return true;
    }
}

