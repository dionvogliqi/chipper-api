<?php

namespace Tests\Feature;

use App\Models\Favorite;
use App\Models\Post;
use App\Models\User;
use App\Notifications\NewPostNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NewPostNotificationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_followers_are_notified_when_user_creates_a_post()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);
        $follower1 = User::factory()->create(['name' => 'Follower 1']);
        $follower2 = User::factory()->create(['name' => 'Follower 2']);

        // Both users follow the author
        Favorite::create([
            'user_id' => $follower1->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);
        Favorite::create([
            'user_id' => $follower2->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        // Author creates a post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My New Post',
            'body' => 'This is the content of my new post.',
        ])->assertCreated();

        // Both followers should receive a notification
        Notification::assertSentTo($follower1, NewPostNotification::class);
        Notification::assertSentTo($follower2, NewPostNotification::class);
    }

    public function test_notification_contains_correct_post_information()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'John Doe']);
        $follower = User::factory()->create(['name' => 'Jane']);

        // Follower follows the author
        Favorite::create([
            'user_id' => $follower->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        // Author creates a post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My Amazing Post',
            'body' => 'Content of the post.',
        ])->assertCreated();

        Notification::assertSentTo(
            $follower,
            NewPostNotification::class,
            function (NewPostNotification $notification) use ($author) {
                return $notification->post->title === 'My Amazing Post'
                    && $notification->post->body === 'Content of the post.'
                    && $notification->post->user_id === $author->id;
            }
        );
    }

    public function test_non_followers_are_not_notified_when_user_creates_a_post()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);
        $nonFollower = User::factory()->create(['name' => 'Non-Follower']);

        // Non-follower does NOT follow the author

        // Author creates a post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My New Post',
            'body' => 'This is the content.',
        ])->assertCreated();

        // Non-follower should NOT receive a notification
        Notification::assertNotSentTo($nonFollower, NewPostNotification::class);
    }

    public function test_author_is_not_notified_of_own_post()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);

        // Author creates a post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My New Post',
            'body' => 'This is the content.',
        ])->assertCreated();

        // Author should NOT receive a notification
        Notification::assertNotSentTo($author, NewPostNotification::class);
    }

    public function test_users_who_favorited_posts_but_not_users_are_not_notified()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);
        $postFavoriter = User::factory()->create(['name' => 'Post Favoriter']);

        // Create a post by author
        $existingPost = Post::factory()->create(['user_id' => $author->id]);

        // User favorites the POST, not the USER
        Favorite::create([
            'user_id' => $postFavoriter->id,
            'post_id' => $existingPost->id,
            'favoritable_id' => $existingPost->id,
            'favoritable_type' => Post::class,
        ]);

        // Author creates another post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'Another New Post',
            'body' => 'More content.',
        ])->assertCreated();

        // Post favoriter should NOT receive a notification (they follow a post, not the user)
        Notification::assertNotSentTo($postFavoriter, NewPostNotification::class);
    }

    public function test_notification_is_sent_via_mail_channel()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);
        $follower = User::factory()->create(['name' => 'Follower']);

        // Follower follows the author
        Favorite::create([
            'user_id' => $follower->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        // Author creates a post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My Post',
            'body' => 'Content.',
        ])->assertCreated();

        Notification::assertSentTo(
            $follower,
            NewPostNotification::class,
            function (NewPostNotification $notification, array $channels) {
                return in_array('mail', $channels);
            }
        );
    }

    public function test_notification_is_queued()
    {
        $author = User::factory()->create(['name' => 'Author']);
        $follower = User::factory()->create(['name' => 'Follower']);

        // Follower follows the author
        Favorite::create([
            'user_id' => $follower->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        $post = Post::factory()->create(['user_id' => $author->id]);

        $notification = new NewPostNotification($post);

        // Assert that the notification implements ShouldQueue
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            $notification
        );
    }

    public function test_multiple_posts_send_multiple_notifications()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);
        $follower = User::factory()->create(['name' => 'Follower']);

        // Follower follows the author
        Favorite::create([
            'user_id' => $follower->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        // Author creates first post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'First Post',
            'body' => 'Content 1.',
        ])->assertCreated();

        // Author creates second post
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'Second Post',
            'body' => 'Content 2.',
        ])->assertCreated();

        // Follower should receive 2 notifications
        Notification::assertSentToTimes($follower, NewPostNotification::class, 2);
    }

    public function test_notification_email_has_correct_subject()
    {
        $author = User::factory()->create(['name' => 'John Smith']);
        $follower = User::factory()->create(['name' => 'Follower']);
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Test Post',
            'body' => 'Test body.',
        ]);

        $notification = new NewPostNotification($post);
        $mailMessage = $notification->toMail($follower);

        $this->assertEquals('New post from John Smith', $mailMessage->subject);
    }

    public function test_notification_email_contains_post_title_and_body()
    {
        $author = User::factory()->create(['name' => 'Author']);
        $follower = User::factory()->create(['name' => 'Reader']);
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Amazing Blog Post',
            'body' => 'This is the blog post content.',
        ]);

        $notification = new NewPostNotification($post);
        $mailMessage = $notification->toMail($follower);

        $this->assertContains('"Amazing Blog Post"', $mailMessage->introLines);
        $this->assertContains('This is the blog post content.', $mailMessage->introLines);
    }

    public function test_notification_email_contains_greeting_with_recipient_name()
    {
        $author = User::factory()->create(['name' => 'Author']);
        $follower = User::factory()->create(['name' => 'Alice']);
        $post = Post::factory()->create(['user_id' => $author->id]);

        $notification = new NewPostNotification($post);
        $mailMessage = $notification->toMail($follower);

        $this->assertEquals('Hello Alice!', $mailMessage->greeting);
    }

    public function test_notification_to_array_returns_correct_data()
    {
        $author = User::factory()->create(['name' => 'Author Name']);
        $follower = User::factory()->create(['name' => 'Follower']);
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Post Title',
            'body' => 'Post body.',
        ]);

        $notification = new NewPostNotification($post);
        $arrayData = $notification->toArray($follower);

        $this->assertEquals($post->id, $arrayData['post_id']);
        $this->assertEquals('Post Title', $arrayData['post_title']);
        $this->assertEquals($author->id, $arrayData['author_id']);
        $this->assertEquals('Author Name', $arrayData['author_name']);
    }

    public function test_creating_post_does_not_delay_api_response()
    {
        // We can't fully test queue timing in a unit test with sync queue,
        // but we can verify the notification implements ShouldQueue
        // which ensures it will be processed asynchronously in production
        
        $post = Post::factory()->create();
        $notification = new NewPostNotification($post);
        
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            $notification,
            'Notification must implement ShouldQueue to avoid blocking API response'
        );
        
        // Also verify the Queueable trait is used
        $this->assertTrue(
            in_array(\Illuminate\Bus\Queueable::class, class_uses_recursive($notification)),
            'Notification must use Queueable trait'
        );
    }

    public function test_user_get_followers_returns_correct_users()
    {
        $author = User::factory()->create(['name' => 'Author']);
        $follower1 = User::factory()->create(['name' => 'Follower 1']);
        $follower2 = User::factory()->create(['name' => 'Follower 2']);
        $nonFollower = User::factory()->create(['name' => 'Non-Follower']);

        // Two users follow the author
        Favorite::create([
            'user_id' => $follower1->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);
        Favorite::create([
            'user_id' => $follower2->id,
            'post_id' => $author->id,
            'favoritable_id' => $author->id,
            'favoritable_type' => User::class,
        ]);

        $followers = $author->getFollowers();

        $this->assertCount(2, $followers);
        $this->assertTrue($followers->contains($follower1));
        $this->assertTrue($followers->contains($follower2));
        $this->assertFalse($followers->contains($nonFollower));
        $this->assertFalse($followers->contains($author));
    }

    public function test_user_with_no_followers_returns_empty_collection()
    {
        $author = User::factory()->create(['name' => 'Author']);

        $followers = $author->getFollowers();

        $this->assertCount(0, $followers);
        $this->assertTrue($followers->isEmpty());
    }

    public function test_notification_not_sent_when_author_has_no_followers()
    {
        Notification::fake();

        $author = User::factory()->create(['name' => 'Author']);

        // Author creates a post without any followers
        $this->actingAs($author)->postJson(route('posts.store'), [
            'title' => 'My New Post',
            'body' => 'This is the content.',
        ])->assertCreated();

        // No notifications should be sent
        Notification::assertNothingSent();
    }
}
