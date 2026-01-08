<?php

namespace App\Observers;

use App\Models\Post;
use App\Notifications\NewPostNotification;
use Illuminate\Support\Facades\Notification;

class PostObserver
{
    public function created(Post $post): void
    {
        $post->loadMissing('user');

        $followers = $post->user->getFollowers();

        if ($followers->isNotEmpty()) {
            Notification::send($followers, new NewPostNotification($post));
        }
    }
}
