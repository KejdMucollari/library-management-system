<?php

namespace App\Http\Controllers;

use App\Models\BookRecommendation;
use App\Services\Ai\BookRecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request, BookRecommendationService $service)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $result = $service->recommend($user->id);

        return back()->with('recommendations', $result);
    }

    public function reset(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        BookRecommendation::query()->where('user_id', $user->id)->delete();

        return back()->with('success', 'Recommendation history cleared.');
    }
}

