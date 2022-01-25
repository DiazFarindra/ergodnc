<?php

namespace Tests\Feature;

use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\OfficePendingApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function itListsAllOfficesInPaginatedWay()
    {
        Office::factory(3)->create();

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        // $response->assertJsonStructure(['meta', 'links']);

        $this->assertNotNull($response->json('meta'));
        $this->assertNotNull($response->json('links'));
        $this->assertNotNull($response->json('data')[0]['id']);
    }

    /**
    * @test
    */
    public function itOnlyListOfficesThatAreNotHiddenAndApproved()
    {
        Office::factory(3)->create();

        Office::factory()->create(['hidden' => true]);
        Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);

        $response = $this->get('/api/offices');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    /**
    * @test
    */
    public function itListOfficesIncludingHiddenAndUnapprovedIfFilteringForTheCurrentLoggedInUser()
    {
        $user = User::factory()->create()->first();

        Office::factory(3)->for($user)->create();

        Office::factory()->hidden()->for($user)->create();
        Office::factory()->pending()->for($user)->create();

        $this->actingAs($user);

        $response = $this->get('/api/offices?user_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    /**
    * @test
    */
    public function itFilterByUserId()
    {
        Office::factory(3)->create();

        $host = User::factory()->create();
        $office = Office::factory()->for($host)->create();

        $response = $this->get('/api/offices?user_id=' . $host->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
    * @test
    */
    public function itFilterByVisitorId()
    {
        Office::factory(3)->create();

        $user = User::factory()->create();
        $office = Office::factory()->create();

        Reservation::factory()->for(Office::factory())->create();
        Reservation::factory()->for($office)->for($user)->create();

        $response = $this->get('/api/offices?visitor_id=' . $user->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
    * @test
    */
    public function itIncludesImagesTagsAndUser()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        $response = $this->get('/api/offices');

        $response->assertOk();

        $this->assertIsArray($response->json('data')[0]['tags']);
        $this->assertIsArray($response->json('data')[0]['images']);
        $this->assertCount(1, $response->json('data')[0]['tags']);
        $this->assertCount(1, $response->json('data')[0]['images']);
        $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }

    /**
    * @test
    */
    public function itReturnsTheNumberOfActiveReservasions()
    {
        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELED]);

        $response = $this->get('/api/offices');

        $response->assertOk();

        $this->assertEquals(1, $response->json('data')[0]['reservations_count']);
    }

    /**
    * @test
    */
    public function itOrdersByDistanceWhenCoordinatesAreProvided()
    {
        // bundaran HI
        // lat : -6.1949772495900755
        // lng : 106.82304573299109

        // Monas Jakarta
        // lat : -6.174827699146143
        // lng : 106.8272222691815

        // Pantai Indah Kapuk
        // lat : -6.090743298962752
        // lng : 106.7448501757005

        $office1 = Office::factory()->create([
            'lat' => '-6.090743298962752',
            'lng' => '106.7448501757005',
            'title' => 'Pantai Indah Kapuk'
        ]);

        $office2 = Office::factory()->create([
            'lat' => '-6.174827699146143',
            'lng' => '106.8272222691815',
            'title' => 'Monas Jakarta'
        ]);

        $response = $this->get('/api/offices?lat=-6.1949772495900755&lng=106.82304573299109');

        $response->assertOk();

        $this->assertEquals('Monas Jakarta', $response->json('data')[0]['title']);
        $this->assertEquals('Pantai Indah Kapuk', $response->json('data')[1]['title']);

        $response = $this->get('/api/offices');

        $this->assertEquals('Pantai Indah Kapuk', $response->json('data')[0]['title']);
        $this->assertEquals('Monas Jakarta', $response->json('data')[1]['title']);
    }

    /**
    * @test
    */
    public function itShowsTheOffice()
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'image.jpg']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]);
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELED]);


        $response = $this->get('/api/offices/' . $office->id);

        $response->assertOk();

        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']);
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
    }

    /**
    * @test
    */
    public function itCreateAnOffice()
    {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->create()->first();
        $tags = Tag::factory(2)->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', Office::factory()->raw([
            'tags' => $tags->pluck('id')->toArray()
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2, 'data.tags');

        $this->assertDatabaseHas('offices', [
            'id' => $response->json('data')['id']
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
    * @test
    */
    public function itDoesntAllowCreatingIfScopeNotProvided()
    {
        $user = User::factory()->createQuietly();

        $token = $user->createToken('test', []);

        $response = $this->postJson('/api/offices', [], [
            'Authorization' => 'Bearer ' . $token->plainTextToken
        ]);

        $response->assertStatus(403);
    }

    /**
    * @test
    */
    public function itUpdateAnOffice()
    {
        $user = User::factory()->create()->first();
        $tags = Tag::factory(3)->create();
        $another_tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'title' => 'updated title',
            'tags' => [$tags[0]->id, $another_tag->id]
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $tags[0]->id)
            ->assertJsonPath('data.tags.1.id', $another_tag->id)
            ->assertJsonPath('data.title', 'updated title');
    }

    /**
    * @test
    */
    public function itDoesntUpdateOfficeThatDoesntBelongToUser()
    {
        $user = User::factory()->create()->first();
        $another_user = User::factory()->create();
        $office = Office::factory()->for($another_user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'title' => 'updated title'
        ]);

        $response->assertStatus(403);
    }

    /**
    * @test
    */
    public function itMarksTheOfficeAsPendingIfDirty()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'lat' => '-6.090743298962752',
            'lng' => '106.7448501757005'
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'approval_status' => Office::APPROVAL_PENDING
        ]);

        Notification::assertSentTo($admin, OfficePendingApproval::class);
    }

    /**
    * @test
    */
    public function itCanDeleteOffices()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertOk();

        $this->assertSoftDeleted($office);
    }

    /**
    * @test
    */
    public function itCannotDeleteAnOfficesThatHasReservations()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertUnprocessable();

        $this->assertDatabaseHas('offices', [
            'id' => $office->id,
            'deleted_at' => null
        ]);
    }

    /**
    * @test
    */
    public function itUpdatedTheFeaturedImageOfAnOffice()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'featured_image_id' => $image->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.featured_image_id', $image->id);
    }

    /**
    * @test
    */
    public function itDoesntUpdateFeaturedImageThatBelongsToAnotherOffice()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id, [
            'featured_image_id' => $image->id,
        ]);

        $response->assertUnprocessable()
            ->assertInvalid('featured_image_id');
    }
}
