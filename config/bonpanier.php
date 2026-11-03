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

        // Taxes du Québec
        'tax.tps_rate' => ['type' => 'rate', 'default' => 5000, 'group' => 'tax'],
        'tax.tvq_rate' => ['type' => 'rate', 'default' => 9975, 'group' => 'tax'],
        'tax.tps_number' => ['type' => 'string', 'default' => '', 'group' => 'tax'],
        'tax.tvq_number' => ['type' => 'string', 'default' => '', 'group' => 'tax'],

        // Paiement
        'interac.email' => ['type' => 'string', 'default' => '', 'group' => 'payment'],

        // Questions ouvertes : comportement provisoire, à confirmer avec l'épicerie
        'wholesale.prices_visible_to' => ['type' => 'enum', 'options' => ['all', 'approved'], 'default' => 'all', 'group' => 'rules', 'open_question' => true],
        'preorder.scope' => ['type' => 'enum', 'options' => ['whole_line', 'missing_part'], 'default' => 'whole_line', 'group' => 'rules', 'open_question' => true],
        'invoice.wholesale_issued_at' => ['type' => 'enum', 'options' => ['handover', 'confirmation'], 'default' => 'handover', 'group' => 'rules', 'open_question' => true],
        'catalog.employees_can_edit' => ['type' => 'bool', 'default' => false, 'group' => 'rules', 'open_question' => true],
    ],
];
