<?php

namespace App\Modules\TaskManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaskManagement\Models\Label;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    private const COLORS = ['slate', 'blue', 'emerald', 'amber', 'rose', 'pink', 'sky', 'violet'];

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeLabels($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', Rule::in(self::COLORS)],
        ]);

        Label::query()->firstOrCreate(
            ['slug' => Str::slug($data['name'])],
            [
                'name' => $data['name'],
                'color' => $data['color'] ?? 'slate',
                'created_by_id' => $request->user()->id,
            ],
        );

        return back()->with('status', 'Label created.');
    }

    public function update(Request $request, Label $label): RedirectResponse
    {
        $this->authorizeLabels($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'color' => ['nullable', Rule::in(self::COLORS)],
        ]);

        $label->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? $label->color,
        ]);

        return back()->with('status', 'Label updated.');
    }

    public function destroy(Request $request, Label $label): RedirectResponse
    {
        $this->authorizeLabels($request);

        $label->delete();

        return back()->with('status', 'Label deleted.');
    }

    private function authorizeLabels(Request $request): void
    {
        abort_unless(
            $request->user()->isAdmin() || $request->user()->hasPermission('tasks.manage-labels'),
            403,
        );
    }
}
