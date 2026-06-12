<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PcMetricsToken extends Model
{
    protected $fillable = [
        'name',
        'token_hash',
        'token_prefix',
        'token_suffix',
        'last_rotated_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_rotated_at' => 'datetime',
        ];
    }

    public function maskedValue(): string
    {
        return $this->token_prefix.'********'.$this->token_suffix;
    }
}
