<?php

namespace App\Console\Commands;

use App\Models\Position;
use Illuminate\Console\Command;

class CloseExpiredPositions extends Command
{
    protected $signature   = 'positions:close-expired';
    protected $description = 'Clôture les scrutins dont la date de fermeture est dépassée';

    public function handle(): void
    {
        $count = Position::where('is_active', true)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->update(['is_active' => false]);

        $this->info("$count scrutin(s) clôturé(s) automatiquement.");
    }
}
