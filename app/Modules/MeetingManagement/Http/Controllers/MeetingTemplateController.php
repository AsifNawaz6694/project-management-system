<?php

namespace App\Modules\MeetingManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MeetingManagement\Models\MeetingTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('meetings/templates/index', [
            'templates' => MeetingTemplate::with('creator:id,name,avatar')
                ->orderBy('name')
                ->get(),
            'kinds' => MeetingTemplate::KINDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        MeetingTemplate::create([
            'created_by_id' => $request->user()->id,
            'name' => $data['name'],
            'kind' => $data['kind'],
            'description' => $data['description'] ?? null,
            'agenda_items' => $data['agenda_items'] ?? [],
            'is_shared' => $data['is_shared'] ?? true,
        ]);

        return back()->with('status', 'Template saved.');
    }

    public function update(Request $request, MeetingTemplate $template): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $template->update($data);

        return back()->with('status', 'Template updated.');
    }

    public function destroy(MeetingTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', 'Template deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(MeetingTemplate::KINDS)],
            'description' => ['nullable', 'string', 'max:2000'],
            'agenda_items' => ['array'],
            'agenda_items.*.title' => ['required_with:agenda_items', 'string', 'max:200'],
            'agenda_items.*.description' => ['nullable', 'string', 'max:1000'],
            'agenda_items.*.time_allocation_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            'is_shared' => ['boolean'],
        ]);
    }
}
