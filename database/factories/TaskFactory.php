<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use App\Modules\TaskManagement\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'todo',
            'priority' => 'medium',
            'position' => 1,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'done',
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'due_date' => now()->subDays(3)->toDateString(),
            'status' => 'todo',
            'completed_at' => null,
        ]);
    }
}
