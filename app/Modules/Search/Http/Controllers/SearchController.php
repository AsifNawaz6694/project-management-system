<?php

namespace App\Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Search\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    /**
     * The full results page.
     */
    public function index(Request $request): Response
    {
        $term = (string) $request->query('q', '');

        return Inertia::render('search/index', [
            'results' => $this->search->search($request->user(), $term, 20),
        ]);
    }

    /**
     * The command palette's data source.
     *
     * The one place in the app that answers JSON rather than an Inertia page:
     * a palette that reloaded the page on every keystroke would be unusable,
     * and there is no page here to return.
     */
    public function quick(Request $request): JsonResponse
    {
        $term = (string) $request->query('q', '');

        return response()->json($this->search->search($request->user(), $term));
    }
}
