<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\ProjectManagement\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->catchPhrase(),
            'description' => fake()->sentence(),
            'status' => 'active',
            'priority' => 'medium',
            'color' => 'blue',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'progress' => 0,
            'owner_id' => User::factory(),
        ];
    }
}
