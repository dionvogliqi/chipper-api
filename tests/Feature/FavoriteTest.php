<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_guest_can_not_favorite_a_post()
    {
        $post = Post::factory()->create();

        $this->postJson(route('favorites.store', ['post' => $post]))
            ->assertStatus(401);
    }

    public function test_a_user_can_favorite_a_post()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'favoritable_id' => $post->id,
            'favoritable_type' => 'App\Models\Post',
        ]);
    }

    public function test_a_user_can_remove_a_post_from_his_favorites()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'favoritable_id' => $post->id,
            'favoritable_type' => 'App\Models\Post',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('favorites.destroy', ['post' => $post]))
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'favoritable_id' => $post->id,
            'favoritable_type' => 'App\Models\Post',
        ]);
    }

    public function test_a_user_can_not_remove_a_non_favorited_item()
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('favorites.destroy', ['post' => $post]))
            ->assertNotFound();
    }

    public function test_a_guest_can_not_favorite_a_user()
    {
        $user = User::factory()->create();

        $this->postJson(route('user-favorites.store', ['user' => $user]))
            ->assertStatus(401);
    }

    public function test_a_user_can_favorite_another_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('user-favorites.store', ['user' => $otherUser]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'post_id' => $otherUser->id,
        ]);
    }

    public function test_a_user_can_remove_a_user_from_his_favorites()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('user-favorites.store', ['user' => $otherUser]))
            ->assertCreated();

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'post_id' => $otherUser->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('user-favorites.destroy', ['user' => $otherUser]))
            ->assertNoContent();

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'post_id' => $otherUser->id,
        ]);
    }

    public function test_a_user_cannot_favorite_himself()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('user-favorites.store', ['user' => $user]))
            ->assertStatus(422)
            ->assertJson(['message' => 'You cannot favorite yourself']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'favoritable_id' => $user->id,
            'favoritable_type' => 'App\Models\User',
        ]);
    }

    public function test_a_user_can_not_remove_a_non_favorited_user()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('user-favorites.destroy', ['user' => $otherUser]))
            ->assertNotFound();
    }
}
