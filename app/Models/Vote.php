<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    /**
     * Les champs autorisés pour l'assignation de masse.
     */
    protected $fillable = [
        'position_id',
        'candidate_id',
        'hash_session',
    ];

    /**
     * Cast des attributs.
     */
    protected function casts(): array
    {
        return [
            'position_id'  => 'integer',
            'candidate_id' => 'integer',
        ];
    }

    /**
     * L'électeur qui a voté (si suivi par user_id, mais ici absent – on garde la relation au cas où).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Le candidat pour lequel le vote a été exprimé.
     */
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function position()
{
    return $this->belongsTo(Position::class);
}
}