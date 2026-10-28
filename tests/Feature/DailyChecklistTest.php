<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ActivityTask;
use App\Models\Role;
use App\Models\TaskComplete;
use App\Models\User;
use App\Support\ChecklistHistory;
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

    public function test_manager_can_add_a_task(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();

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
        $user = User::factory()->role(Role::MANAGER)->create();

        $this->actingAs($user)
            ->from(route('operational.checklist'))
            ->post(route('operational.checklist.tasks.store'), [
                'task_name' => 'Bad task',
                'priority' => 'urgent',
            ])
            ->assertSessionHasErrors('priority');
    }

    public function test_staff_can_complete_a_task_without_self_verification(): void
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
            'verified_by_id' => null,
            'verified_at' => null,
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
        $task = ActivityTask::create(['task_name' => 'Guest delete', 'priority' => 'low']);

        $this->delete(route('operational.checklist.tasks.destroy', $task))->assertRedirect(route('login'));
    }

    public function test_administrator_can_delete_a_task_without_completion_history(): void
    {
        $user = User::factory()->admin()->create();
        $task = ActivityTask::create(['task_name' => 'Delete me', 'priority' => 'low']);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertDatabaseHas('activity_logs', [
            'activity_type' => ActivityLog::TYPE_TASK_DELETED,
            'user_id' => $user->id,
        ]);
    }

    public function test_manager_can_delete_a_task_without_completion_history(): void
    {
        $user = User::factory()->role(Role::MANAGER)->create();
        $task = ActivityTask::create(['task_name' => 'Manager delete', 'priority' => 'low']);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('activity_tasks', ['task_id' => $task->task_id]);
    }

    public function test_staff_cannot_delete_a_task(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create(['task_name' => 'Staff cannot delete', 'priority' => 'low']);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_TASK_DELETED)->count());
    }

    public function test_task_with_completion_history_cannot_be_deleted(): void
    {
        $user = User::factory()->admin()->create();
        $task = ActivityTask::create(['task_name' => 'Keep history', 'priority' => 'low']);

        TaskComplete::create([
            'task_id' => $task->task_id,
            'user_id' => $user->id,
            'verified_by_id' => $user->id,
            'completion_date' => now()->toDateString(),
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('operational.checklist.tasks.destroy', $task))
            ->assertRedirect(route('operational.checklist'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('activity_tasks', ['task_id' => $task->task_id]);
        $this->assertSame(0, ActivityLog::where('activity_type', ActivityLog::TYPE_TASK_DELETED)->count());
    }

    public function test_checklist_hides_the_delete_action_from_unauthorized_roles(): void
    {
        ActivityTask::create(['task_name' => 'Visible task', 'priority' => 'low']);

        $staff = User::factory()->create();

        $this->actingAs($staff)
            ->get(route('operational.checklist'))
            ->assertOk()
            ->assertDontSee('Delete Permanently');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('operational.checklist'))
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

    public function test_history_tab_lists_past_records_and_shows_the_selected_record_details(): void
    {
        $staff = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $manager = User::factory()->role(Role::MANAGER)->create(['name' => 'Maria Santos']);

        $taskA = ActivityTask::create(['task_name' => 'Inspect seedlings in Block A', 'priority' => 'high']);
        ActivityTask::create(['task_name' => 'Clean nursery paths', 'priority' => 'low']);

        TaskComplete::create([
            'task_id' => $taskA->task_id,
            'user_id' => $staff->id,
            'verified_by_id' => $manager->id,
            'completion_date' => '2026-10-10',
            'verified_at' => '2026-10-10 08:15:00',
            'remark' => 'No signs of wilting.',
        ]);

        $response = $this->actingAs($staff)
            ->get(route('operational.checklist', ['tab' => 'history']));

        $response->assertOk()
            ->assertSee('History')
            ->assertSee('Oct 10, 2026')
            ->assertSee('October 10, 2026')
            ->assertSee('Staff: Juan Dela Cruz')
            ->assertSee('Maria Santos')
            ->assertSee('Manager')
            ->assertSee('No signs of wilting.')
            ->assertSee('8:15 AM')
            ->assertSee('View Full Details')
            ->assertSee('id="historyDetailsModal"', false)
            ->assertSee(route('operational.checklist.export', ['date' => '2026-10-10']), false);
    }

    public function test_history_marks_a_record_completed_only_when_every_task_is_done(): void
    {
        $user = User::factory()->create();
        $taskA = ActivityTask::create(['task_name' => 'Task A', 'priority' => 'high']);
        $taskB = ActivityTask::create(['task_name' => 'Task B', 'priority' => 'low']);

        // Partial day: only Task A completed.
        TaskComplete::create([
            'task_id' => $taskA->task_id,
            'user_id' => $user->id,
            'verified_by_id' => $user->id,
            'completion_date' => '2026-10-08',
            'verified_at' => '2026-10-08 07:00:00',
        ]);

        // Complete day: every listed task completed.
        foreach ([$taskA, $taskB] as $task) {
            TaskComplete::create([
                'task_id' => $task->task_id,
                'user_id' => $user->id,
                'verified_by_id' => $user->id,
                'completion_date' => '2026-10-09',
                'verified_at' => '2026-10-09 07:00:00',
            ]);
        }

        $records = ChecklistHistory::records();

        $this->assertSame(2, $records->total());
        $this->assertSame('2026-10-09', $records->items()[0]['date']->toDateString());
        $this->assertSame(ChecklistHistory::STATUS_COMPLETED, $records->items()[0]['status']);
        $this->assertSame(2, $records->items()[0]['completed_count']);
        $this->assertSame(2, $records->items()[0]['total_count']);
        $this->assertSame('2026-10-08', $records->items()[1]['date']->toDateString());
        $this->assertSame(ChecklistHistory::STATUS_PENDING, $records->items()[1]['status']);
        $this->assertSame(1, $records->items()[1]['completed_count']);
        $this->assertSame(2, $records->items()[1]['total_count']);

        $pendingOnly = ChecklistHistory::records(['status' => ChecklistHistory::STATUS_PENDING]);
        $this->assertSame(1, $pendingOnly->total());
        $this->assertSame('2026-10-08', $pendingOnly->items()[0]['date']->toDateString());

        $completedOnly = ChecklistHistory::records(['status' => ChecklistHistory::STATUS_COMPLETED]);
        $this->assertSame(1, $completedOnly->total());
        $this->assertSame('2026-10-09', $completedOnly->items()[0]['date']->toDateString());
    }

    public function test_history_filters_by_search_and_date_range(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Reyes']);
        $bob = User::factory()->create(['name' => 'Bob Santos']);

        $water = ActivityTask::create(['task_name' => 'Water the seedling trays', 'priority' => 'medium']);
        $grafts = ActivityTask::create(['task_name' => 'Inspect grafts', 'priority' => 'high']);

        TaskComplete::create([
            'task_id' => $water->task_id,
            'user_id' => $alice->id,
            'verified_by_id' => $alice->id,
            'completion_date' => '2026-10-01',
            'verified_at' => '2026-10-01 07:00:00',
        ]);

        TaskComplete::create([
            'task_id' => $grafts->task_id,
            'user_id' => $bob->id,
            'verified_by_id' => $bob->id,
            'completion_date' => '2026-10-05',
            'verified_at' => '2026-10-05 07:00:00',
        ]);

        $this->assertSame(1, ChecklistHistory::records(['search' => 'Alice'])->total());
        $this->assertSame('2026-10-01', ChecklistHistory::records(['search' => 'Alice'])->items()[0]['date']->toDateString());
        $this->assertSame(1, ChecklistHistory::records(['search' => 'grafts'])->total());
        $this->assertSame('2026-10-05', ChecklistHistory::records(['search' => 'grafts'])->items()[0]['date']->toDateString());

        $fromThird = ChecklistHistory::records(['date_from' => '2026-10-03']);
        $this->assertSame(1, $fromThird->total());
        $this->assertSame('2026-10-05', $fromThird->items()[0]['date']->toDateString());

        $toThird = ChecklistHistory::records(['date_to' => '2026-10-03']);
        $this->assertSame(1, $toThird->total());
        $this->assertSame('2026-10-01', $toThird->items()[0]['date']->toDateString());

        $this->assertSame(0, ChecklistHistory::records(['date_from' => '2026-10-03', 'date_to' => '2026-10-04'])->total());
    }

    public function test_history_detail_reports_open_tasks_as_not_done_and_writes_nothing(): void
    {
        $user = User::factory()->create();
        $taskA = ActivityTask::create(['task_name' => 'Task A', 'priority' => 'high']);
        $taskB = ActivityTask::create(['task_name' => 'Task B', 'priority' => 'low']);

        TaskComplete::create([
            'task_id' => $taskA->task_id,
            'user_id' => $user->id,
            'verified_by_id' => $user->id,
            'completion_date' => '2026-10-02',
            'verified_at' => '2026-10-02 09:30:00',
            'remark' => 'Checked.',
        ]);

        $record = ChecklistHistory::record('2026-10-02');

        $this->assertNotNull($record);
        $this->assertSame(ChecklistHistory::STATUS_PENDING, $record['status']);
        $this->assertNull(ChecklistHistory::record('2026-10-03'));

        $statusByTask = collect($record['tasks'])->mapWithKeys(
            fn (array $row) => [$row['task']->task_id => $row['status']]
        );

        $this->assertSame(ChecklistHistory::STATUS_COMPLETED, $statusByTask[$taskA->task_id]);
        $this->assertSame(ChecklistHistory::STATUS_NOT_DONE, $statusByTask[$taskB->task_id]);

        $this->actingAs($user)
            ->get(route('operational.checklist', ['tab' => 'history', 'history_date' => '2026-10-02']))
            ->assertOk()
            ->assertSee('Not Done')
            ->assertSee('Completed task details are read-only; a Manager can still record verification.');

        $this->assertDatabaseCount('task_completes', 1);
        $this->assertDatabaseCount('activity_tasks', 2);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_history_filters_apply_automatically_and_show_an_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('operational.checklist', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('id="historyFilterForm"', false)
            ->assertSee('data-auto-filter', false)
            ->assertSee('id="historySearch"', false)
            ->assertSee('id="historyStatus"', false)
            ->assertSee('Clear Filters')
            ->assertDontSee('bi-funnel')
            ->assertDontSee('Apply Filters')
            ->assertSee('No checklist records found for the current filters.')
            ->assertSee('Select a record to review its tasks, remarks and verification status.');
    }

    public function test_history_pagination_keeps_the_active_filters(): void
    {
        $user = User::factory()->create(['name' => 'Filtered Staff']);
        $task = ActivityTask::create(['task_name' => 'Daily task', 'priority' => 'medium']);

        foreach (range(1, 11) as $offset) {
            TaskComplete::create([
                'task_id' => $task->task_id,
                'user_id' => $user->id,
                'verified_by_id' => $user->id,
                'completion_date' => now()->subDays($offset)->toDateString(),
                'verified_at' => now()->subDays($offset),
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('operational.checklist', ['tab' => 'history', 'search' => 'Filtered']));

        $response->assertOk()
            ->assertSee('11 records')
            ->assertSee('search=Filtered', false);

        $secondPage = $this->actingAs($user)
            ->get(route('operational.checklist', ['tab' => 'history', 'search' => 'Filtered', 'page' => 2]));

        $secondPage->assertOk()
            ->assertSee(now()->subDays(11)->format('M j, Y'));
    }
}
