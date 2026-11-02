<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Dashboard\UserDailyNotes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserDailyNotesTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(11, 0));
        $this->operator = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active']);
        $this->actingAs($this->operator);
    }

    public function test_text_is_saved_only_on_ok_and_multiple_notes_share_the_day(): void
    {
        $page = Livewire::test(UserDailyNotes::class)->set('newContent', '  Prima nota  ');
        $this->assertDatabaseCount('user_daily_notes', 0);
        $this->assertDatabaseCount('user_daily_note_entries', 0);
        $page->call('addEntry')->assertHasNoErrors()->assertSet('newContent', '')->assertSee('Prima nota')->assertSee('Modifica')->assertSee('Elimina');
        $page->set('newContent', "Seconda nota\nsu due righe")->call('addEntry')->assertHasNoErrors()->assertSet('newContent', '');
        $this->assertDatabaseCount('user_daily_notes', 1);
        $this->assertDatabaseCount('user_daily_note_entries', 2);
        $this->assertDatabaseHas('user_daily_note_entries', ['content'=>'Prima nota']);
        $this->assertDatabaseHas('user_daily_note_entries', ['content'=>"Seconda nota\nsu due righe"]);
        $this->get(route('daily-notes.index'))->assertOk()->assertSee('Scrivi una nota')->assertDontSee('Aggiungi una voce')->assertDontSee('Annotazione aggiuntiva');
    }

    public function test_edit_requires_ok_and_cancel_preserves_text_and_existing_details(): void
    {
        $day = $this->operator->dailyNotes()->create(['date'=>today()]);
        $entry = $day->entries()->create(['content'=>'Testo originale', 'post_script'=>'Annotazione già presente', 'sort_order'=>1]);
        $item = $entry->checklistItems()->create(['label'=>'Attività già presente', 'is_completed'=>true]);
        $page = Livewire::test(UserDailyNotes::class)->call('editEntry', $entry->id)->set('editingContent', 'Bozza');
        $this->assertSame('Testo originale', $entry->fresh()->content);
        $page->call('cancelEdit')->assertSet('editingEntryId', null);
        $this->assertSame('Testo originale', $entry->fresh()->content);
        $page->call('editEntry', $entry->id)->set('editingContent', 'Nota aggiornata')->call('saveEntry')->assertHasNoErrors()->assertSet('editingEntryId', null)->assertSee('Nota aggiornata');
        $this->assertSame('Nota aggiornata', $entry->fresh()->content);
        $this->assertSame('Annotazione già presente', $entry->fresh()->post_script);
        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_every_operator_can_delete_own_notes_but_cannot_read_or_change_others(): void
    {
        $mine = $this->operator->dailyNotes()->create(['date'=>today()])->entries()->create(['content'=>'Nota personale']);
        $other = User::factory()->create(['role'=>UserRole::Operator, 'status'=>'active'])->dailyNotes()->create(['date'=>today()])->entries()->create(['content'=>'Nota privata di un altro operatore']);
        Livewire::test(UserDailyNotes::class)->assertDontSee($other->content)->call('editEntry', $other->id)->assertNotFound();
        Livewire::test(UserDailyNotes::class)->call('deleteEntry', $other->id)->assertNotFound();
        Livewire::test(UserDailyNotes::class)->call('deleteEntry', $mine->id)->assertHasNoErrors()->assertSee('Nota eliminata.');
        $this->assertModelMissing($mine);
        $this->assertModelExists($other);
    }

    public function test_empty_and_oversized_notes_are_rejected_without_losing_saved_text(): void
    {
        Livewire::test(UserDailyNotes::class)->set('newContent', " \n ")->call('addEntry')->assertHasErrors(['newContent'=>'required']);
        Livewire::test(UserDailyNotes::class)->set('newContent', str_repeat('a', 10001))->call('addEntry')->assertHasErrors(['newContent'=>'max']);
        $this->assertDatabaseCount('user_daily_note_entries', 0);
        $this->assertDatabaseCount('user_daily_notes', 0);
        $entry = $this->operator->dailyNotes()->create(['date'=>today()])->entries()->create(['content'=>'Da conservare']);
        Livewire::test(UserDailyNotes::class)->call('editEntry', $entry->id)->set('editingContent', '   ')->call('saveEntry')->assertHasErrors(['editingContent'=>'required']);
        $this->assertSame('Da conservare', $entry->fresh()->content);
    }

    public function test_opening_and_changing_day_never_create_or_delete_notes(): void
    {
        $day = $this->operator->dailyNotes()->create(['date'=>today()->subDay()]);
        $entry = $day->entries()->create(['content'=>'Nota di ieri']);
        $empty = $day->entries()->create(['content'=>'']);
        Livewire::test(UserDailyNotes::class)->assertDontSee('Nota di ieri')->call('previousDay')->assertSee('Nota di ieri')->call('nextDay')->assertDontSee('Nota di ieri');
        $this->assertDatabaseCount('user_daily_notes', 1);
        $this->assertModelExists($entry);
        $this->assertModelExists($empty);
    }

    public function test_note_content_is_rendered_as_text(): void
    {
        $text = '<script>alert("nota")</script>';
        Livewire::test(UserDailyNotes::class)->set('newContent', $text)->call('addEntry')->assertSee(e($text), false)->assertDontSee($text, false);
    }
}
