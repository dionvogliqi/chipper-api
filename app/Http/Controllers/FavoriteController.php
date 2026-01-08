<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Favorite;
use Illuminate\Http\Request;
use App\Http\Requests\CreateFavoriteRequest;
use Illuminate\Http\Response;

/**
 * @group Favorites
 *
 * API endpoints for managing favorites
 */
class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $favorites = $request->user()->favorites;
        return FavoriteResource::collection($favorites);
    }

    public function store(CreateFavoriteRequest $request, Post $post)
    {
        $request->user()->favorites()->create([
            'post_id' => $post->id,
            'favoritable_id' => $post->id,
            'favoritable_type' => Post::class,
        ]);

        return response()->noContent(Response::HTTP_CREATED);
    }


    public function destroy(Request $request, Post $post)
    {
        $favorite = $request->user()->favorites()->where('favoritable_id', $post->id)
            ->where('favoritable_type', Post::class)->firstOrFail();

        $favorite->delete();

        return response()->noContent();
    }

}
