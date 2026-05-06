<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Produits;

final class ProduitsManager
{
    public function validate(Produits $produit): bool
    {
        if (trim($produit->getNom()) === '') {
            throw new \InvalidArgumentException('Le nom du produit est obligatoire');
        }

        if ($produit->getPrixUnitaire() <= 0) {
            throw new \InvalidArgumentException('Le prix unitaire doit être supérieur à zéro');
        }

        if ($produit->getQuantite() < 0) {
            throw new \InvalidArgumentException('La quantité ne peut pas être négative');
        }

        if ($produit->isPromoActive()) {
            $code = trim((string) ($produit->getPromoCode() ?? ''));
            if ($code === '') {
                throw new \InvalidArgumentException('Le code promo est obligatoire si la promo est active');
            }

            $discount = $produit->getPromoDiscountPercent();
            if ($discount === null || $discount < 1 || $discount > 100) {
                throw new \InvalidArgumentException('Le pourcentage de remise doit être entre 1 et 100');
            }

            $start = $produit->getPromoStartAt();
            $end = $produit->getPromoEndAt();
            if ($start === null || $end === null) {
                throw new \InvalidArgumentException('Les dates de promo sont obligatoires si la promo est active');
            }
            if ($start > $end) {
                throw new \InvalidArgumentException('La date de début promo doit être antérieure à la date de fin');
            }
        }

        if ($produit->isRental()) {
            $pricePerDay = $produit->getRentalPricePerDay();
            if ($pricePerDay === null || $pricePerDay <= 0) {
                throw new \InvalidArgumentException('Le prix de location par jour doit être supérieur à zéro');
            }
        }

        return true;
    }
}

