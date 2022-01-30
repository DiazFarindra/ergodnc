<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @test
     */
    public function itUploadsAnImageAndStoredItUnderTheOffice()
    {
        Storage::fake();

        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/' . $office->id . '/images', [
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::assertExists(
            $response->json('data.path')
        );
    }

    /**
    * @test
    */
    public function itDeleteAnImage()
    {
        Storage::put('/office_image.jpg', 'empty');

        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id . '/images/' . $image->id);

        $response->assertOk();

        $this->assertModelMissing($image);

        Storage::assertMissing('office_image.jpg');
    }

    /**
    * @test
    */
    public function itDoesntDeleteThatBelongsToAnotherResource()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id . '/images/' . $image->id);

        $response->assertNotFound();
    }

    /**
    * @test
    */
    public function itDoesntDeleteTheOnlyImage()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id . '/images/' . $image->id);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'cannot delete the only image']);
    }

    /**
    * @test
    */
    public function itDoesntDeleteTheFeaturedImage()
    {
        $user = User::factory()->create()->first();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $office->images()->create([
            'path' => 'image.jpg'
        ]);

        $office->update([
            'featured_image_id' => $image->id
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/' . $office->id . '/images/' . $image->id);

        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'cannot delete the featured image']);
    }
}
