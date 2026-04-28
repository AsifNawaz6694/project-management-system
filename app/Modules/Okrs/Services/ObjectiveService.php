<?php

namespace App\Modules\Okrs\Services;

use App\Models\User;
use App\Modules\Okrs\Models\KeyResult;
use App\Modules\Okrs\Models\KrUpdate;
use App\Modules\Okrs\Models\Objective;
use App\Modules\UserManagement\Models\Activity;
use Illuminate\Support\Facades\DB;

class ObjectiveService
{
    public function create(array $data, User $owner): Objective
    {
        return DB::transaction(function () use ($data, $owner) {
            $objective = Objective::create([
                'owner_id' => $data['owner_id'] ?? $owner->id,
                'parent_id' => $data['parent_id'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'period' => $data['period'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'status' => $data['status'] ?? 'draft',
                'visibility' => $data['visibility'] ?? 'company',
            ]);

            $this->replaceKeyResults($objective, $data['key_results'] ?? []);
            $this->recomputeProgress($objective);

            Activity::log('okr.objective-created', [
                'module' => 'okrs',
                'description' => "Created objective \"{$objective->title}\" for {$objective->period}",
                'properties' => ['objective_id' => $objective->id],
            ]);

            return $objective->load('keyResults');
        });
    }

    public function update(Objective $objective, array $data): Objective
    {
        return DB::transaction(function () use ($objective, $data) {
            $objective->fill(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'period' => $data['period'] ?? null,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'status' => $data['status'] ?? null,
                'visibility' => $data['visibility'] ?? null,
                'team_id' => $data['team_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'owner_id' => $data['owner_id'] ?? null,
                'parent_id' => $data['parent_id'] ?? null,
            ], fn ($v) => $v !== null));
            $objective->save();

            if (array_key_exists('key_results', $data)) {
                $this->replaceKeyResults($objective, $data['key_results']);
            }

            $this->recomputeProgress($objective);

            Activity::log('okr.objective-updated', [
                'module' => 'okrs',
                'description' => "Updated objective \"{$objective->title}\"",
                'properties' => ['objective_id' => $objective->id],
            ]);

            return $objective->refresh()->load('keyResults');
        });
    }

    public function delete(Objective $objective): void
    {
        $title = $objective->title;
        $objective->delete();
        Activity::log('okr.objective-deleted', [
            'module' => 'okrs',
            'description' => "Deleted objective \"{$title}\"",
            'properties' => ['objective_id' => $objective->id],
        ]);
    }

    public function recordKrUpdate(KeyResult $kr, array $data, User $actor): KrUpdate
    {
        return DB::transaction(function () use ($kr, $data, $actor) {
            $update = $kr->updates()->create([
                'recorded_by_id' => $actor->id,
                'value' => $data['value'],
                'confidence' => $data['confidence'] ?? $kr->status,
                'note' => $data['note'] ?? null,
                'recorded_at' => $data['recorded_at'] ?? now(),
            ]);

            $kr->update([
                'current_value' => $data['value'],
                'status' => $data['confidence'] ?? $kr->status,
            ]);

            $this->recomputeProgress($kr->objective);

            Activity::log('okr.kr-updated', [
                'module' => 'okrs',
                'description' => "Updated KR \"{$kr->title}\" → {$data['value']}",
                'properties' => ['key_result_id' => $kr->id, 'objective_id' => $kr->objective_id, 'kr_update_id' => $update->id],
            ]);

            return $update->load('recorder');
        });
    }

    private function replaceKeyResults(Objective $objective, array $keyResults): void
    {
        $objective->keyResults()->delete();

        foreach (array_values($keyResults) as $i => $row) {
            if (empty(trim((string) ($row['title'] ?? '')))) {
                continue;
            }

            KeyResult::create([
                'objective_id' => $objective->id,
                'owner_id' => $row['owner_id'] ?? $objective->owner_id,
                'title' => $row['title'],
                'description' => $row['description'] ?? null,
                'metric_type' => $row['metric_type'] ?? 'number',
                'start_value' => $row['start_value'] ?? 0,
                'target_value' => $row['target_value'] ?? 100,
                'current_value' => $row['current_value'] ?? ($row['start_value'] ?? 0),
                'unit' => $row['unit'] ?? null,
                'position' => $i,
                'status' => $row['status'] ?? 'on_track',
            ]);
        }
    }

    public function recomputeProgress(Objective $objective): void
    {
        $krs = $objective->keyResults()->get();
        if ($krs->isEmpty()) {
            $objective->update(['progress' => 0]);

            return;
        }

        $avg = (int) round($krs->avg(fn (KeyResult $k) => $k->progress));
        $objective->update(['progress' => $avg]);
    }
}
