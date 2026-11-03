<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'gerant';
    case Employee = 'employe';

    public function label(): string
    {
        return __('app.roles.'.$this->value);
    }
}
