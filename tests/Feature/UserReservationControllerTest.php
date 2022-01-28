<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListReservationsThatBelongsToTheUser()
    {
        $user = User::factory()->create()->first();

        $reservation = Reservation::factory()->for($user)->create();

        $image = $reservation->office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $reservation->office()->update([
            'featured_image_id' => $image->id
        ]);

        Reservation::factory(2)->for($user)->create();
        Reservation::factory(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }
}
