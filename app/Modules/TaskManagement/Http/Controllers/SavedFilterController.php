<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\SavedFilter;
use App\Modules\TaskManagement\Services\TaskQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedFilterController extends Controller
{
    public function __construct(private readonly TaskQueryService $queries) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'query' => ['required', 'array'],
            'is_shared' => ['boolean'],
        ]);

        SavedFilter::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            [
                // Persist only recognised keys, so a saved filter cannot carry
                // arbitrary input back into the query builder later.
                'query' => $this->queries->sanitise($data['query']),
                'is_shared' => (bool) ($data['is_shared'] ?? false),
            ],
        );

        return back()->with('status', 'Filter saved.');
    }

    public function destroy(Request $request, SavedFilter $savedFilter): RedirectResponse
    {
        abort_unless(
            $savedFilter->user_id === $request->user()->id || $request->user()->isAdmin(),
            403,
        );

        $savedFilter->delete();

        return back()->with('status', 'Filter deleted.');
    }
}
