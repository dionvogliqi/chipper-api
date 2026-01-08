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

    public function test_favorites_index_returns_posts_and_users_grouped()
    {
        $user = User::factory()->create();
        $postAuthor = User::factory()->create(['name' => 'Jack']);
        $post = Post::factory()->create([
            'title' => 'All about cats',
            'body' => 'Lorem Ipsum...',
            'user_id' => $postAuthor->id,
        ]);
        $favoritedUser = User::factory()->create(['name' => 'Jane']);

        // Favorite a post
        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        // Favorite a user
        $this->actingAs($user)
            ->postJson(route('user-favorites.store', ['user' => $favoritedUser]))
            ->assertCreated();

        // Get favorites and verify structure
        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'posts' => [
                    '*' => ['id', 'title', 'body', 'user' => ['id', 'name']],
                ],
                'users' => [
                    '*' => ['id', 'name'],
                ],
            ],
        ]);

        $response->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $post->id,
                        'title' => 'All about cats',
                        'body' => 'Lorem Ipsum...',
                        'user' => [
                            'id' => $postAuthor->id,
                            'name' => 'Jack',
                        ],
                    ],
                ],
                'users' => [
                    [
                        'id' => $favoritedUser->id,
                        'name' => 'Jane',
                    ],
                ],
            ],
        ]);
    }

    public function test_favorites_index_returns_empty_arrays_when_no_favorites()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk();

        $response->assertJson([
            'data' => [
                'posts' => [],
                'users' => [],
            ],
        ]);
    }

    public function test_favorites_index_returns_only_posts_when_no_user_favorites()
    {
        $user = User::factory()->create();
        $postAuthor = User::factory()->create(['name' => 'Author']);
        $post = Post::factory()->create([
            'title' => 'Test Post',
            'body' => 'Test Body',
            'user_id' => $postAuthor->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('favorites.store', ['post' => $post]))
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk();

        $response->assertJson([
            'data' => [
                'posts' => [
                    [
                        'id' => $post->id,
                        'title' => 'Test Post',
                        'body' => 'Test Body',
                        'user' => [
                            'id' => $postAuthor->id,
                            'name' => 'Author',
                        ],
                    ],
                ],
                'users' => [],
            ],
        ]);
    }

    public function test_favorites_index_returns_only_users_when_no_post_favorites()
    {
        $user = User::factory()->create();
        $favoritedUser = User::factory()->create(['name' => 'Luke']);

        $this->actingAs($user)
            ->postJson(route('user-favorites.store', ['user' => $favoritedUser]))
            ->assertCreated();

        $response = $this->actingAs($user)
            ->getJson(route('favorites.index'))
            ->assertOk();

        $response->assertJson([
            'data' => [
                'posts' => [],
                'users' => [
                    [
                        'id' => $favoritedUser->id,
                        'name' => 'Luke',
                    ],
                ],
            ],
        ]);
    }

    public function test_guest_cannot_access_favorites_index()
    {
        $this->getJson(route('favorites.index'))
            ->assertStatus(401);
    }
}
