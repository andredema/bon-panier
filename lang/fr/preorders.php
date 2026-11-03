<?php

return [
    'title' => 'Précommandes',
    'intro' => 'Servies automatiquement à chaque entrée en stock, dans l’ordre des commandes.',
    'pending' => 'À servir',
    'empty' => 'Aucune précommande en attente.',
    'col_product' => 'Produit',
    'col_lines' => 'Commandes',
    'col_qty' => 'Quantité attendue',
    'col_available' => 'Disponible',
    'col_arrival' => 'Prochain arrivage',
    'no_arrival' => 'aucun annoncé',
    'customers' => 'Clients, dans l’ordre de service',
    'line' => ':number · :name · :qty :unit',
    'since' => 'commandé le :date',
    'serve' => 'Servir maintenant',
    'serve_hint' => 'Le stock disponible couvre au moins la première précommande (stock libéré par une annulation, par exemple).',
    'serve_notify' => 'Aviser les clients par courriel',
    'served_now' => ':count précommande servie.|:count précommandes servies.',
    'served_none' => 'Aucune précommande ne pouvait être servie : stock disponible insuffisant.',
    'recent' => 'Servies récemment',
    'recent_empty' => 'Aucune précommande servie pour l’instant.',
    'served_on' => 'servie le :date',
    'by_receipt' => 'entrée :number',
    'by_hand' => 'servie à la main',
    'notified' => 'client avisé',
    'not_notified' => 'client non avisé',

    'receipt' => [
        'pending' => ':count précommande en attente (:qty :unit)|:count précommandes en attente (:qty :unit)',
        'will_serve' => ':count précommande servie|:count précommandes servies',
        'left_for_sale' => ':qty :unit disponibles à la vente',
        'box_title' => 'Précommandes servies à la validation',
        'box_none' => 'Aucune précommande en attente pour ces produits',
        'box_line' => ':name : :served sur :pending',
        'served_title' => 'Précommandes servies par cette entrée',
        'served_none' => 'Aucune précommande servie par cette entrée.',
    ],

    'tracking' => [
        'arrived' => 'précommande arrivée',
        'waiting' => 'précommande, arrive le :date',
        'waiting_no_date' => 'précommande, arrivage à confirmer',
    ],

    'mail' => [
        'subject' => 'Votre précommande est arrivée — commande :number',
        'arrived' => 'Bonne nouvelle : les produits que vous avez précommandés (commande :number) viennent d’arriver à l’épicerie.',
        'preparing' => 'Nous préparons votre commande ; nous vous écrirons quand elle sera prête.',
        'still_waiting' => ':count autre produit de votre commande est encore attendu ; nous vous écrirons à son arrivée.|:count autres produits de votre commande sont encore attendus ; nous vous écrirons à leur arrivée.',
    ],
];
