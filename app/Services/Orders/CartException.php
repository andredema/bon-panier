<?php

namespace App\Services\Orders;

use RuntimeException;

/** Ajout refusé (minimum, pas, stock…) : le message est affiché tel quel au client. */
class CartException extends RuntimeException {}
