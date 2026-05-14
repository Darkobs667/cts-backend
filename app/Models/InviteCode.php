<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'used', 'used_by', 'created_by'];

    protected $casts = ['used' => 'boolean'];

    public function usedByUser()
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
