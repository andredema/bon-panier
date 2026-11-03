<?php

namespace App\Enums;

enum Channel: string
{
    case Retail = 'detail';
    case Wholesale = 'gros';

    public function label(): string
    {
        return __('catalog.channels.'.$this->value);
    }
}
