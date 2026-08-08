<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_voter_can_apply_with_a_photo_stored_on_the_local_public_disk(): void
    {
        Storage::fake('public');
        config()->set('cloudinary.driver', 'local');
        config()->set('cloudinary.url', null);

        $voter = User::query()->create([
            'first_name' => 'Awa',
            'last_name' => 'Diop',
            'email' => 'awa@example.test',
            'password' => 'password',
            'role' => 'electeur',
            'status' => 'Validé',
        ]);
        $position = Position::query()->create([
            'title' => 'Présidence',
            'is_active' => true,
        ]);

        $response = $this->actingAs($voter, 'api')->post('/api/apply', [
            'position_id' => $position->id,
            'bio' => 'Je souhaite représenter les étudiants.',
            'slogan' => 'Ensemble pour le CTS',
            'photo' => UploadedFile::fake()->image('portrait.jpg'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'en_attente');

        $candidate = Candidate::query()->sole();
        $this->assertNull($candidate->photo_public_id);
        $this->assertStringStartsWith('candidates/', $candidate->photo_path);
        Storage::disk('public')->assertExists($candidate->photo_path);
    }

    public function test_an_admin_update_persists_and_returns_the_updated_candidate(): void
    {
        $admin = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'CTS',
            'email' => 'admin@example.test',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'Validé',
        ]);
        $voter = User::query()->create([
            'first_name' => 'Moussa',
            'last_name' => 'Traoré',
            'email' => 'moussa@example.test',
            'password' => 'password',
            'role' => 'electeur',
            'status' => 'Validé',
        ]);
        $position = Position::query()->create(['title' => 'Trésorerie', 'is_active' => true]);
        $candidate = Candidate::query()->create([
            'user_id' => $voter->id,
            'position_id' => $position->id,
            'slogan' => 'Ancien slogan',
            'bio' => 'Ancienne présentation',
            'status' => 'valide',
        ]);

        $response = $this->actingAs($admin, 'api')->putJson("/api/candidates/{$candidate->id}", [
            'user_id' => $voter->id,
            'position_id' => $position->id,
            'slogan' => 'Un nouveau cap',
            'bio' => 'Une présentation actualisée',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $candidate->id)
            ->assertJsonPath('data.slogan', 'Un nouveau cap');
        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'slogan' => 'Un nouveau cap',
            'bio' => 'Une présentation actualisée',
        ]);
    }
}
