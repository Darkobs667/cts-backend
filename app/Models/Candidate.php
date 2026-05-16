<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'position_id', 'bio','status', 'photo_path'])]

class Candidate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    use HasFactory;

        /**
        * Get the user that owns the candidate.
        */
   // ... tes imports

public function user()
{
    return $this->belongsTo(User::class);
}

/**
 * La relation inverse : Un candidat postule à une position.
 */
public function position()
{
    return $this->belongsTo(Position::class);
}

/**
 * Relation avec les votes pour ce candidat.
 * Utilisée avec withCount('votes') pour optimiser les performances des résultats.
 */
public function votes()
{
    return $this->hasMany(Vote::class);
}}
