<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPostNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Post $post
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New post from {$this->post->user->name}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("{$this->post->user->name} just published a new post:")
            ->line("\"{$this->post->title}\"")
            ->line($this->post->body)
            ->line('Thank you for using Chipper!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
            'author_id' => $this->post->user_id,
            'author_name' => $this->post->user->name,
        ];
    }
}
