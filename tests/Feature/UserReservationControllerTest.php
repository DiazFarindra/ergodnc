<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UserReservationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @test
     */
    public function itListReservationsThatBelongsToTheUser()
    {
        $user = User::factory()->create()->first();

        [$reservation] = Reservation::factory(2)->for($user)->create();

        $image = $reservation->office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
            ->assertJsonPath('data.0.office.featured_image.id', $image->id);
    }

    /**
     * @test
     */
    public function itListReservationFilteredByDateRange()
    {
        $user = User::factory()->create()->first();

        $fromDate = '2021-03-03';
        $toDate = '2021-04-04';

        // within the date range
        // ...
        $reservation1 = Reservation::factory()->for($user)->create([
            'start_date' => '2021-03-01',
            'end_date' => '2021-03-15'
        ]);

        $reservation2 = Reservation::factory()->for($user)->create([
            'start_date' => '2021-03-25',
            'end_date' => '2021-04-15'
        ]);

        $reservation3 = Reservation::factory()->for($user)->create([
            'start_date' => '2021-03-25',
            'end_date' => '2021-03-29'
        ]);

        // within the date range but belongs to different user
        // ...
        Reservation::factory()->create([
            'start_date' => '2021-03-25',
            'end_date' => '2021-03-29'
        ]);

        // outside the date range
        // ...
        Reservation::factory()->for($user)->create([
            'start_date' => '2021-02-25',
            'end_date' => '2021-03-01'
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2021-05-01',
            'end_date' => '2021-05-01'
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'from_date' => $fromDate,
            'to_date' => $toDate
        ]));

        $response
            ->assertJsonCount(3, 'data');

        $this->assertEquals([$reservation1->id, $reservation2->id, $reservation3->id], collect($response->json('data'))->pluck('id')->toArray());
    }

    /**
    * @test
    */
    public function itFiltersResultByStatus()
    {
        $user = User::factory()->create()->first();

        $reservation = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE
        ]);

        Reservation::factory()->for($user)->canceled()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'status' => Reservation::STATUS_ACTIVE
        ]));

        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }

    /**
    * @test
    */
    public function itFiltersResultByOffice()
    {
        $user = User::factory()->create()->first();

        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($office)->for($user)->create();

        Reservation::factory()->for($user)->canceled()->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
            'office_id' => $office->id
        ]));

        $response
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id);
    }
}
