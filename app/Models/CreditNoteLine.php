<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

class CreditNoteLine extends Model
{
    use Immutable;

    public $timestamps = false;

    protected $fillable = ['credit_note_id', 'invoice_line_id', 'label', 'amount_cents', 'is_taxable'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'is_taxable' => 'boolean'];
    }
}
