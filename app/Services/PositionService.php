<?php

namespace App\Services;

use App\Models\Position;
use Illuminate\Database\Eloquent\Collection;

class PositionService
{
    public function create(array $data): Position
    {
        return Position::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'is_active'   => $data['is_active'] ?? true,
            'closes_at'   => $data['closes_at'] ?? null,
            'quorum'      => $data['quorum'] ?? null,
        ]);
    }

    public function getAll(): Collection
    {
        return Position::with(['candidates.user'])->get();
    }

    public function update(Position $position, array $data): bool
    {
        return $position->update($data);
    }

    public function toggleStatus(Position $position): void
    {
        $position->is_active = !$position->is_active;

        if ($position->is_active && !$position->started_at) {
            $position->started_at = now();
        } elseif (!$position->is_active) {
            $position->started_at = null;
        }

        $position->save();
    }

    public function delete(Position $position): bool
    {
        $position->candidates()->delete();
        return $position->delete();
    }

    public function getActivePositions(): Collection
    {
        return Position::where('is_active', true)->get();
    }
}
