<?php

/*
| Réglages de l'épicerie : valeurs par défaut. Le gérant les modifie dans
| Gestion › Réglages ; les valeurs enregistrées (table settings) priment.
|
| Taux de taxe en millièmes de pour cent (5 % = 5000, 9,975 % = 9975) :
| aucun float, et le calcul des taxes reste exact au cent.
|
| Les réglages marqués « open_question » répondent à une question encore ouverte
| du cahier des charges (section 13) : le comportement par défaut est provisoire.
*/

return [
    'settings' => [
        // Commerce
        'shop.name' => ['type' => 'string', 'default' => 'Épicerie africaine Le Bon Panier', 'group' => 'shop'],
        'shop.address' => ['type' => 'string', 'default' => '840, avenue du Marché, Saint-Lin–Laurentides (Québec)', 'group' => 'shop'],
        'shop.phone' => ['type' => 'string', 'default' => '', 'group' => 'shop'],
        'shop.email' => ['type' => 'string', 'default' => '', 'group' => 'shop'],
        // Loi 25 : personne responsable de la protection des renseignements personnels
        'shop.privacy_officer' => ['type' => 'string', 'default' => '', 'group' => 'shop'],

        // Ramassage et livraison (horaires et zone : questions ouvertes)
        'pickup.days' => ['type' => 'days', 'default' => '2,3,4,5,6', 'group' => 'pickup', 'open_question' => true],
        'pickup.times' => ['type' => 'slots', 'default' => '10:00-12:00, 14:00-16:00, 16:00-18:00', 'group' => 'pickup', 'open_question' => true],
        'pickup.lead_hours' => ['type' => 'int', 'default' => 12, 'min' => 0, 'max' => 168, 'group' => 'pickup'],
        'pickup.horizon_days' => ['type' => 'int', 'default' => 7, 'min' => 1, 'max' => 30, 'group' => 'pickup'],
        'delivery.enabled' => ['type' => 'bool', 'default' => false, 'group' => 'pickup', 'open_question' => true],
        'delivery.fee' => ['type' => 'money', 'default' => 0, 'group' => 'pickup', 'open_question' => true],
        'delivery.zone' => ['type' => 'string', 'default' => '', 'group' => 'pickup', 'open_question' => true],
        'delivery.frozen' => ['type' => 'bool', 'default' => false, 'group' => 'pickup', 'open_question' => true],
        'delivery.taxable' => ['type' => 'bool', 'default' => false, 'group' => 'pickup', 'open_question' => true],
        // Délai de paiement inscrit sur la facture (date d'échéance), en jours ; 0 = payable à la réception
        'invoice.due_days' => ['type' => 'int', 'default' => 14, 'min' => 0, 'max' => 90, 'group' => 'payment', 'open_question' => true],
        'payment.on_handover' => ['type' => 'bool', 'default' => true, 'group' => 'payment', 'open_question' => true],

        // Taxes du Québec
        'tax.tps_rate' => ['type' => 'rate', 'default' => 5000, 'group' => 'tax'],
        'tax.tvq_rate' => ['type' => 'rate', 'default' => 9975, 'group' => 'tax'],
        'tax.tps_number' => ['type' => 'string', 'default' => '', 'group' => 'tax'],
        'tax.tvq_number' => ['type' => 'string', 'default' => '', 'group' => 'tax'],
        // Comptable : destinataire des exports « Tout envoyer au comptable » (question ouverte)
        'accounting.email' => ['type' => 'string', 'default' => '', 'group' => 'tax', 'open_question' => true],

        // Paiement
        'interac.email' => ['type' => 'string', 'default' => '', 'group' => 'payment'],

        // Questions ouvertes : comportement provisoire, à confirmer avec l'épicerie
        'wholesale.prices_visible_to' => ['type' => 'enum', 'options' => ['all', 'approved'], 'default' => 'all', 'group' => 'rules', 'open_question' => true],
        'preorder.scope' => ['type' => 'enum', 'options' => ['whole_line', 'missing_part'], 'default' => 'whole_line', 'group' => 'rules', 'open_question' => true],
        'invoice.wholesale_issued_at' => ['type' => 'enum', 'options' => ['handover', 'confirmation'], 'default' => 'handover', 'group' => 'rules', 'open_question' => true],
        'catalog.employees_can_edit' => ['type' => 'bool', 'default' => false, 'group' => 'rules', 'open_question' => true],
    ],
];
