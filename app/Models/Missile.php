<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Missile extends Model
{
    use HasFactory;

    protected $fillable = [
        'partie_id',
        'coordonnées',
        'resultat'
    ];

    public function partie(): BelongsTo
    {
        return $this->belongsTo(Partie::class, 'partie_id');
    }


}
