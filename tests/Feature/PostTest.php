<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_guest_can_not_create_a_post()
    {
        $response = $this->postJson(route('posts.store'), [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);

        $response->assertStatus(401);
    }

    public function test_a_user_can_create_a_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'body',
                ]
            ])
            ->assertJson([
                'data' => [
                    'title' => 'Test Post',
                    'body' => 'This is a test post.',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'body' => 'This is a test post.',
        ]);
    }

    public function test_a_user_can_update_a_post()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Original title',
            'body' => 'Original body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($user)->putJson(route('posts.update', ['post' => $id]), [
            'title' => 'Updated title',
            'body' => 'Updated body.',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'title' => 'Updated title',
                    'body' => 'Updated body.',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Updated title',
            'body' => 'Updated body.',
            'id' => $id,
        ]);
    }

    public function test_a_user_can_not_update_a_post_by_other_user()
    {
        $john = User::factory()->create(['name' => 'John']);
        $jack = User::factory()->create(['name' => 'Jack']);

        $response = $this->actingAs($john)->postJson(route('posts.store'), [
            'title' => 'Original title',
            'body' => 'Original body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($jack)->putJson(route('posts.update', ['post' => $id]), [
            'title' => 'Updated title',
            'body' => 'Updated body.',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'title' => 'Original title',
            'body' => 'Original body.',
            'id' => $id,
        ]);
    }

    public function test_a_user_can_destroy_one_of_his_posts()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'My title',
            'body' => 'My body.',
        ]);

        $id = Arr::get($response->json(), 'data.id');

        $response = $this->actingAs($user)->deleteJson(route('posts.destroy', ['post' => $id]));

        $response->assertNoContent();

        $this->assertDatabaseMissing('posts', [
            'id' => $id,
        ]);
    }

    public function test_a_user_can_create_a_post_with_an_image()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $image = UploadedFile::fake()->image('test-image.jpg');

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Post with Image',
            'body' => 'This is a post with an image.',
            'image' => $image,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'body', 'image_url',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Post with Image',
            'body' => 'This is a post with an image.',
        ]);

        Storage::disk('public')->assertExists('posts/' . $image->hashName());
        $this->assertNotNull(Arr::get($response->json(), 'data.image_url'));
    }

    public function test_a_user_can_create_a_post_without_an_image()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('posts.store'), [
            'title' => 'Post without Image',
            'body' => 'This is a post without an image.',
        ]);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'title' => 'Post without Image',
                    'body' => 'This is a post without an image.',
                    'image_url' => null,
                ]
            ]);
    }

    public function test_image_validation_rejects_invalid_file_types()
    {
        $user = User::factory()->create();
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'Post with Invalid File',
            'body' => 'This should fail validation.',
            'image' => $invalidFile,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_image_validation_accepts_allowed_file_types()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($allowedExtensions as $extension) {
            $image = UploadedFile::fake()->image("test-image.{$extension}");

            $response = $this->actingAs($user)->post(route('posts.store'), [
                'title' => "Post with {$extension} Image",
                'body' => "Testing {$extension} upload.",
                'image' => $image,
            ]);

            $response->assertCreated();
            $this->assertNotNull(Arr::get($response->json(), 'data.image_url'));
        }
    }
}
