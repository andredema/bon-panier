<?php

namespace App\Services\Orders;

use RuntimeException;

/** Changement de statut refusé : le message est affiché tel quel à l'équipe. */
class WorkflowException extends RuntimeException {}
