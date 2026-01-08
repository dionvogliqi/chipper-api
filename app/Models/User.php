<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Favorite;
use App\Models\Post;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function favoritedPosts(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable')->where('favoritable_type', Post::class);
    }

    public function favoritedUsers(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable')->where('favoritable_type', User::class);
    }

    public function followers(): MorphMany
    {
        return $this->morphMany(Favorite::class, 'favoritable');
    }

    public function getFollowers(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereIn('id', $this->followers()->pluck('user_id'))->get();
    }
}
