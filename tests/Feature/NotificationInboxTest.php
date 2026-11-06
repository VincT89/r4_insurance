<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Notifications\NotificationDropdown;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    private function notices(User $user, int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $time = now()->subMinutes($count - $i);
            $notice = $user->notifications()->create(['id'=>(string) Str::uuid(), 'type'=>'inbox-check', 'data'=>['title'=>'Avviso '.$i, 'message'=>'Controllo della casella personale'], 'created_at'=>$time, 'updated_at'=>$time]);
            $ids[] = $notice->id;
        }
        return array_reverse($ids);
    }

    public function test_every_notification_can_be_reached_without_loading_other_users_alerts(): void
    {
        $user = User::factory()->create(['role'=>UserRole::Operator]);
        $ids = $this->notices($user, 25);
        $this->notices(User::factory()->create(['role'=>UserRole::Admin]), 3);
        $component = Livewire::actingAs($user)->test(NotificationDropdown::class)
            ->assertViewHas('notificationsTotal', 25)->assertViewHas('unreadNotificationsCount', 25)
            ->assertViewHas('latestNotifications', fn ($rows) => $rows->modelKeys() === array_slice($ids, 0, 10));
        $component->call('nextNotificationsPage')->assertViewHas('notificationPage', 2)
            ->assertViewHas('unreadNotificationsCount', 25)
            ->assertViewHas('latestNotifications', fn ($rows) => $rows->modelKeys() === array_slice($ids, 10, 10));
        $component->call('nextNotificationsPage')->assertViewHas('notificationPage', 3)
            ->assertViewHas('latestNotifications', fn ($rows) => $rows->modelKeys() === array_slice($ids, 20));
        $component->call('nextNotificationsPage')->assertViewHas('notificationPage', 3);
        $component->call('previousNotificationsPage')->assertViewHas('notificationPage', 2);
        $component->call('markAllAsRead')->assertViewHas('notificationPage', 1)->assertViewHas('unreadNotificationsCount', 0);
        $this->assertSame(25, $user->notifications()->whereNotNull('read_at')->count());
    }

    public function test_an_emptied_page_returns_to_the_first_available_alerts(): void
    {
        $user = User::factory()->create(['role'=>UserRole::Operator]);
        $ids = $this->notices($user, 11);
        $component = Livewire::actingAs($user)->test(NotificationDropdown::class)->call('nextNotificationsPage')->assertViewHas('notificationPage', 2);
        $user->notifications()->whereKey($ids[10])->delete();
        $component->call('$refresh')->assertViewHas('notificationPage', 1)->assertViewHas('notificationsTotal', 10)
            ->assertViewHas('unreadNotificationsCount', 10);
    }
}
