<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Favorite;
use Illuminate\Http\Request;
use App\Http\Requests\CreateFavoriteRequest;
use Illuminate\Http\Response;

class UserFavoriteController extends Controller
{
    public function store(CreateFavoriteRequest $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'You cannot favorite yourself'], 422);
        }

        try {
            $favorite = new Favorite([
                'user_id' => $request->user()->id,
                'post_id' => $user->id,
                'favoritable_id' => $user->id,
                'favoritable_type' => User::class,
            ]);
            $favorite->save();
        } catch (\Exception $e) {
            \Log::error('Failed to create user favorite: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create favorite'], 500);
        }

        return response()->noContent(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, User $user)
    {
        $favorite = $request->user()->favorites()->where('post_id', $user->id)->firstOrFail();

        $favorite->delete();

        return response()->noContent();
    }
}
