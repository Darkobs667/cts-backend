<?php

namespace App\Models;
use App\Services\CandidatePhotoService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'position_id', 'bio', 'slogan', 'status', 'photo_path', 'photo_public_id'])]

class Candidate extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    use HasFactory;

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return CandidatePhotoService::deliveryUrl($this->photo_path);
    }

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
