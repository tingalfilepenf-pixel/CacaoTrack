<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ActivityTask;
use App\Models\Role;
use App\Models\TaskComplete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_checklist_to_login(): void
    {
        $this->get(route('operational.checklist'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_checklist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.checklist'))
            ->assertOk()
            ->assertSee('Daily Checklist');
    }

    public function test_authenticated_user_can_add_a_task(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('operational.checklist.tasks.store'), [
            'task_name' => 'Inspect seedlings',
            'description' => 'Check block A',
            'priority' => 'high',
        ]);

        $response->assertRedirect(route('operational.checklist'));
        $this->assertDatabaseHas('activity_tasks', [
            'task_name' => 'Inspect seedlings',
            'priority' => 'high',
        ]);
    }

    public function test_task_requires_a_valid_priority(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('operational.checklist'))
            ->post(route('operational.checklist.tasks.store'), [
                'task_name' => 'Bad task',
                'priority' => 'urgent',
            ])
            ->assertSessionHasErrors('priority');
    }

    public function test_user_can_complete_a_task(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Clean paths', 'priority' => 'low']);

        $response = $this->actingAs($user)->post(route('operational.checklist.tasks.complete', $task), [
            'remark' => 'Done before noon.',
        ]);

        $response->assertRedirect(route('operational.checklist'));
        $this->assertDatabaseHas('task_completes', [
            'task_id' => $task->task_id,
            'user_id' => $user->id,
            'verified_by_id' => $user->id,
            'remark' => 'Done before noon.',
        ]);
    }

    public function test_task_cannot_be_completed_twice_in_the_same_day(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Inspect grafts', 'priority' => 'medium']);

        $this->actingAs($user)->post(route('operational.checklist.tasks.complete', $task))->assertRedirect();
        $this->actingAs($user)->post(route('operational.checklist.tasks.complete', $task))->assertRedirect();

        $this->assertSame(1, TaskComplete::where('task_id', $task->task_id)->count());
    }

    public function test_user_can_archive_a_task(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Clean nursery paths', 'priority' => 'low']);

        $this->actingAs($user)
            ->post(route('operational.checklist.tasks.archive', $task))
            ->assertRedirect(route('operational.checklist'));

        $this->assertNotNull($task->fresh()->archived_at);
        $this->assertTrue($task->fresh()->isArchived());
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_TASK_ARCHIVED,
            'user_id' => $user->id,
        ]);
    }

    public function test_archived_task_is_hidden_from_the_active_checklist(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Hidden archived task', 'priority' => 'low']);

        $this->actingAs($user)->post(route('operational.checklist.tasks.archive', $task));

        $this->actingAs($user)
            ->get(route('operational.checklist'))
            ->assertOk()
            ->assertDontSee('Hidden archived task');
    }

    public function test_archived_tasks_are_listed_on_the_archived_view(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Archived listing task', 'priority' => 'medium']);

        $this->actingAs($user)->post(route('operational.checklist.tasks.archive', $task));

        $this->actingAs($user)
            ->get(route('operational.checklist', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Archived listing task')
            ->assertSee('Archived');
    }

    public function test_user_can_restore_an_archived_task(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Restore me', 'priority' => 'high']);

        $this->actingAs($user)->post(route('operational.checklist.tasks.archive', $task));
        $this->actingAs($user)
            ->post(route('operational.checklist.tasks.restore', $task))
            ->assertRedirect(route('operational.checklist', ['status' => 'archived']));

        $this->assertNull($task->fresh()->archived_at);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_TASK_RESTORED,
            'user_id' => $user->id,
        ]);
        $this->actingAs($user)
            ->get(route('operational.checklist'))
            ->assertOk()
            ->assertSee('Restore me');
    }

    public function test_archiving_an_already_archived_task_is_rejected(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Once only', 'priority' => 'low', 'archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('operational.checklist.tasks.archive', $task))
            ->assertRedirect(route('operational.checklist'))
            ->assertSessionHas('error');

        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_TASK_ARCHIVED)->count());
    }

    public function test_archived_task_cannot_be_completed(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Not completable', 'priority' => 'low', 'archived_at' => now()]);

        $this->actingAs($user)
            ->post(route('operational.checklist.tasks.complete', $task))
            ->assertRedirect(route('operational.checklist'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('task_completes', 0);
    }

    public function test_guests_are_redirected_from_deleting_a_task(): void
    {
        $task = ActivityTask::create(['task_name' => 'Guest delete', 'priority' => 'low', 'archived_at' => now()]);

        $this->delete(route('operational.checklist.tasks.destroy', $task))->assertRedirect(route('login'));
    }

    public function test_administrator_can_delete_an_archived_task_without_completion_history(): void
    {
        $user = User::factory()->admin()->create();
        $task = ActivityTask::create(['task_name' => 'Delete me', 'priority' => 'low', 'archived_at' => now()]);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist', ['status' => 'archived']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_TASK_DELETED,
            'user_id' => $user->id,
        ]);
    }

    public function test_manager_can_delete_an_archived_task_without_completion_history(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $task = ActivityTask::create(['task_name' => 'Manager delete', 'priority' => 'low', 'archived_at' => now()]);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist', ['status' => 'archived']))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_tasks', ['task_id' => $task->task_id]);
    }

    public function test_staff_cannot_delete_a_task(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Staff cannot delete', 'priority' => 'low', 'archived_at' => now()]);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_TASK_DELETED)->count());
    }

    public function test_archived_task_with_completion_history_cannot_be_deleted(): void
    {
        $user = User::factory()->admin()->create();
        $task = ActivityTask::create(['task_name' => 'Keep history', 'priority' => 'low', 'archived_at' => now()]);

        TaskComplete::create([
            'task_id' => $task->task_id,
            'user_id' => $user->id,
            'verified_by_id' => $user->id,
            'completion_date' => now()->toDateString(),
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist', ['status' => 'archived']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_TASK_DELETED)->count());
    }

    public function test_active_task_cannot_be_deleted(): void
    {
        $user = User::factory()->admin()->create();
        $task = ActivityTask::create(['task_name' => 'Active task', 'priority' => 'low']);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist', ['status' => 'archived']))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_TASK_DELETED)->count());
    }

    public function test_archived_view_hides_the_delete_action_from_unauthorized_roles(): void
    {
        ActivityTask::create(['task_name' => 'Archived visible', 'priority' => 'low', 'archived_at' => now()]);

        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('operational.checklist', ['status' => 'archived']))
            ->assertOk()
            ->assertDontSee('Delete Permanently');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('operational.checklist', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Delete Permanently');
    }

    public function test_guests_are_redirected_from_the_pdf_export(): void
    {
        $this->get(route('operational.checklist.export'))->assertRedirect(route('login'));
    }

    public function test_pdf_export_returns_a_pdf_with_real_saved_data(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Fertilizer application', 'priority' => 'high']);

        $this->actingAs($user)->post(route('operational.checklist.tasks.complete', $task), [
            'remark' => 'Applied NPK.',
        ]);

        $response = $this->actingAs($user)->get(route('operational.checklist.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        // The checklist PDF must contain the real saved task and remark.
        $response->assertSee('Fertilizer application', false);
        $response->assertSee('Applied NPK.', false);
    }

    public function test_pdf_export_rejects_a_future_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.checklist.export', ['date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('date');
    }

    public function test_pdf_export_rejects_an_invalid_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.checklist.export', ['date' => 'not-a-date']))
            ->assertSessionHasErrors('date');
    }
}
