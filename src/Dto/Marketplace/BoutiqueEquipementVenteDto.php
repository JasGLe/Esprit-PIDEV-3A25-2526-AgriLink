<?php

namespace App\Dto\Marketplace;

/**
 * Données du formulaire « Mettre en vente » (équipement → boutique), validées côté serveur.
 */
class BoutiqueEquipementVenteDto
{
    public string $condition = 'NEUF';

    public mixed $prix = null;

    public mixed $quantite = null;

    public string $warranty_enabled = '0';

    public ?string $warranty_duration = null;

    public mixed $used_value = null;

    public string $used_unit = 'mois';
}
