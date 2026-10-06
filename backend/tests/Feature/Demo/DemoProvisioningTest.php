<?php

namespace Tests\Feature\Demo;

use App\Actions\Demo\ProvisionDemoEnvironment;
use App\Enums\TaskPriority;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DemoProvisioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeated_provisioning_does_not_duplicate_demo_records(): void
    {
        $provision = app(ProvisionDemoEnvironment::class);
        $firstWorkspace = $provision->handle();
        $firstCounts = $this->demoCounts($firstWorkspace);

        $secondWorkspace = $provision->handle();

        $this->assertSame($firstWorkspace->id, $secondWorkspace->id);
        $this->assertSame($firstCounts, $this->demoCounts($secondWorkspace));
        $this->assertSame([
            'users' => 3,
            'workspaces' => 1,
            'memberships' => 3,
            'projects' => 1,
            'sprints' => 2,
            'tasks' => 8,
            'comments' => 3,
            'activities' => 16,
            'messages' => 4,
            'notifications' => 0,
        ], $firstCounts);
    }

    public function test_reset_restores_only_the_known_demo_workspace(): void
    {
        $provision = app(ProvisionDemoEnvironment::class);
        $demoWorkspace = $provision->handle();
        $ordinaryWorkspace = Workspace::factory()->create(['slug' => 'ordinary-workspace']);
        $ordinaryProject = $ordinaryWorkspace->projects()->create([
            'name' => 'Ordinary Project',
            'slug' => 'ordinary-project',
        ]);
        $demoProject = $demoWorkspace->projects()->sole();
        $demoProject->tasks()->create([
            'title' => 'Visitor-created task',
            'priority' => TaskPriority::Low,
        ]);

        $provision->handle(reset: true);

        $this->assertDatabaseMissing('tasks', ['title' => 'Visitor-created task']);
        $this->assertDatabaseHas('workspaces', ['id' => $ordinaryWorkspace->id]);
        $this->assertDatabaseHas('projects', ['id' => $ordinaryProject->id]);
        $this->assertDatabaseHas('tasks', ['title' => 'Validate guided workspace entry']);
    }

    /** @return array<string, int> */
    private function demoCounts(Workspace $workspace): array
    {
        $projectIds = $workspace->projects()->pluck('id');
        $taskIds = Task::query()->whereIn('project_id', $projectIds)->pluck('id');

        return [
            'users' => User::query()->whereIn('email', [
                ProvisionDemoEnvironment::USER_EMAIL,
                'maya@demo.syncora.invalid',
                'jordan@demo.syncora.invalid',
            ])->count(),
            'workspaces' => Workspace::query()
                ->where('slug', ProvisionDemoEnvironment::WORKSPACE_SLUG)
                ->count(),
            'memberships' => $workspace->memberships()->count(),
            'projects' => $projectIds->count(),
            'sprints' => $workspace->projects()->withCount('sprints')->get()->sum('sprints_count'),
            'tasks' => $taskIds->count(),
            'comments' => TaskComment::query()->whereIn('task_id', $taskIds)->count(),
            'activities' => TaskActivity::query()->whereIn('task_id', $taskIds)->count(),
            'messages' => ChatMessage::query()->whereIn('project_id', $projectIds)->count(),
            'notifications' => UserNotification::query()->whereIn('project_id', $projectIds)->count(),
        ];
    }
}
