<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Candidate;
use App\Models\Vote;

#[Fillable(['title', 'is_active', 'started_at', 'closes_at', 'description', 'quorum'])]
class Position extends Model
{
    use HasFactory;

    /**
     * Get the candidates for the position.
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
