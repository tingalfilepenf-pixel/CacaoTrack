<?php

namespace Tests\Feature;

use App\Models\ActivityTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_success_flash_renders_a_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['success' => 'Stock-in transaction recorded successfully.'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('toast-container', false)
            ->assertSee('Stock-in transaction recorded successfully.');
    }

    public function test_an_error_flash_renders_a_toast(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['error' => 'Unable to record the stock-in transaction.'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('toast-container', false)
            ->assertSee('Unable to record the stock-in transaction.');
    }

    public function test_no_toast_container_is_rendered_without_a_flash(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('toast-container', false);
    }

    public function test_a_real_operation_surfaces_its_feedback_as_a_toast(): void
    {
        $user = User::factory()->create();
        $task = ActivityTask::create([
            'task_name' => 'Water nursery',
            'description' => 'Water all seedlings.',
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->post(route('operational.checklist.tasks.complete', $task))
            ->assertRedirect(route('operational.checklist'));

        $this->followingRedirects()
            ->actingAs($user)
            ->get(route('operational.checklist'))
            ->assertSee('toast-container', false)
            ->assertSee('Task marked as completed.');
    }
}
