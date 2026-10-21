<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Appearance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_colors_are_personal_persisted_and_safely_applied_to_new_panels(): void
    {
        $user=User::factory()->create();
        $colors=array_replace(Appearance::defaults(),['section'=>'#c4d9ee','section_end'=>'#edf4fc','form_border'=>'#125b9a','primary_hover'=>'#123d67']);
        $this->actingAs($user)->patch(route('appearance.update'),['colors'=>$colors])->assertSessionHasNoErrors();
        $this->get(route('appearance.edit'))->assertOk()->assertSee('--ap-section:#c4d9ee;',false)
            ->assertSee('--ap-section-end:#edf4fc;',false)->assertSee('--ap-primary-hover:#123d67;',false)->assertSee('Anteprima riquadri e pulsanti');
        $this->assertSame('#c4d9ee',$user->fresh()->appearance['section']);
        $legacy=Appearance::variables(['primary'=>'#125b9a','panel'=>'#ffffff']);
        $this->assertNotSame(Appearance::defaults()['section'],$legacy['--ap-section']);
        $unsafe=Appearance::variables(['section'=>'#ffffff','section_end'=>'#000000']);
        $this->assertSame('#ffffff',$unsafe['--ap-section-gradient-end']);
    }

    public function test_section_heading_keeps_contrast_across_the_entire_personal_gradient(): void
    {
        foreach (['#ffffff', '#666666', '#777777', '#000000', '#0b498c'] as $panel) {
            foreach (['#ffffff', '#000000', '#ffff00', '#0b498c'] as $primary) {
                $vars = Appearance::variables(['panel'=>$panel, 'primary'=>$primary]);
                foreach (['--ap-section', '--ap-section-gradient-end'] as $surface) {
                    $this->assertGreaterThanOrEqual(4.5, Appearance::contrast($vars['--ap-section-heading'], $vars[$surface]));
                }
            }
        }
    }

    public function test_every_role_can_save_only_its_own_theme(): void
    {
        $other = User::factory()->create();
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);
            $colors = array_replace(Appearance::defaults(), ['sidebar' => '#ABCDEF']);
            $this->actingAs($user)->get(route('appearance.edit'))->assertOk()->assertSee('Aspetto');
            $this->patch(route('appearance.update'), ['colors' => $colors])->assertSessionHasNoErrors()->assertRedirect(route('appearance.edit'));
            $this->assertSame('#abcdef', $user->fresh()->appearance['sidebar']);
            $this->assertNull($other->fresh()->appearance);
        }
    }

    public function test_theme_survives_navigation_and_does_not_leak_to_another_user(): void
    {
        $user = User::factory()->create();
        $colors = array_replace(Appearance::defaults(), ['page' => '#153759', 'primary' => '#ffff00']);
        $this->actingAs($user)->patch(route('appearance.update'), ['colors' => $colors])->assertSessionHasNoErrors();
        $this->get('/profile')->assertOk()->assertSee('--ap-page:#153759;', false)->assertSee('--ap-primary-text:#000000;', false);
        $this->actingAs(User::factory()->create())->get('/profile')->assertOk()->assertDontSee('--ap-page:#153759;', false);
    }

    public function test_invalid_or_unexpected_color_values_do_not_change_the_theme(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['red', '#fff', '#123456;display:none', '</style><script>alert(1)</script>', ['#123456']] as $invalid) {
            $this->from(route('appearance.edit'))->patch(route('appearance.update'), ['colors' => array_replace(Appearance::defaults(), ['page' => $invalid])])->assertSessionHasErrors('colors.page');
            $this->assertNull($user->fresh()->appearance);
        }
        $this->patch(route('appearance.update'), ['colors' => array_merge(Appearance::defaults(), ['extra' => '#ffffff'])])->assertSessionHasErrors('colors');
        $this->patch(route('appearance.update'), ['colors' => ['page' => '#ffffff']])->assertSessionHasErrors('colors.sidebar');
        $this->assertNull($user->fresh()->appearance);
    }

    public function test_another_user_id_is_rejected_and_account_fields_are_not_updated(): void
    {
        $user = User::factory()->create(['role' => UserRole::Operator]);
        $other = User::factory()->create();
        $this->actingAs($user)->patch(route('appearance.update'), ['colors' => Appearance::defaults(), 'user_id' => $other->id])->assertSessionHasErrors('user_id');
        $this->assertNull($other->fresh()->appearance);
        $this->patch(route('appearance.update'), ['colors' => Appearance::defaults(), 'role' => 'admin', 'email' => 'changed@example.test'])->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Operator, $user->fresh()->role);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_reset_restores_defaults_only_for_current_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $colors = array_replace(Appearance::defaults(), ['panel' => '#112233']);
        $other->forceFill(['appearance' => $colors])->save();
        $user->forceFill(['appearance' => $colors])->save();
        $this->actingAs($user)->delete(route('appearance.destroy'))->assertRedirect(route('appearance.edit'));
        $this->assertNull($user->fresh()->appearance);
        $this->assertSame($colors, $other->fresh()->appearance);
        $this->get(route('appearance.edit'))->assertOk()->assertSee('--ap-panel:#ffffff;', false);
    }

    public function test_appearance_requires_authentication(): void
    {
        $this->get(route('appearance.edit'))->assertRedirect('/login');
        $this->patch(route('appearance.update'), ['colors' => Appearance::defaults()])->assertRedirect('/login');
        $this->delete(route('appearance.destroy'))->assertRedirect('/login');
    }

    public function test_text_contrast_is_preserved_for_light_dark_and_midrange_backgrounds(): void
    {
        foreach (['#ffffff', '#000000', '#777777', '#ff0000', '#00ff00', '#0000ff', '#ffff00', '#123456'] as $background) {
            $colors = array_fill_keys(array_keys(Appearance::FIELDS), $background);
            $vars = Appearance::variables($colors);
            foreach (['page', 'panel', 'field', 'sidebar', 'sidebar-active', 'sidebar-hover', 'secondary-hover', 'topbar', 'table-header', 'table-body', 'table-hover', 'login-panel'] as $surface) {
                foreach (['text', 'muted', 'heading', 'accent', 'link-hover'] as $kind) {
                    $this->assertGreaterThanOrEqual(4.5, Appearance::contrast($vars['--ap-'.$surface.'-'.$kind], $background));
                }
                $this->assertGreaterThanOrEqual(3, Appearance::contrast($vars['--ap-'.$surface.'-border'], $background));
            }
            $this->assertGreaterThanOrEqual(4.5, Appearance::contrast($vars['--ap-primary-text'], $background));
            $this->assertGreaterThanOrEqual(4.5, Appearance::contrast($vars['--ap-primary-hover-text'], $vars['--ap-primary-hover']));
        }
        $this->assertSame(Appearance::defaults(), Appearance::colors(['page' => 'url(https://invalid.test)', 'unknown' => '#ffffff']));
        foreach ([['#0b498c', '#ffffff'], ['#ffff00', '#101010'], ['#ffffff', '#777777']] as [$primary, $panel]) {
            $vars = Appearance::variables(['primary' => $primary, 'panel' => $panel]);
            foreach (['text', 'muted', 'heading', 'accent', 'link-hover'] as $kind) {
                $this->assertGreaterThanOrEqual(4.5, Appearance::contrast($vars['--ap-section-'.$kind], $vars['--ap-section']));
            }
        }
    }

    public function test_login_uses_supplied_brand_and_favicon_without_old_marketing_claims(): void
    {
        $this->get('/login')->assertOk()->assertSee('images/agency-signature.svg')->assertSee('Soluzioni Assicurative SAS')->assertSee('favicon-32x32.png?v=')->assertSee('favicon.ico?v=')->assertDontSee('Sodano Consulting')->assertDontSee('sodanoconsulting.it')->assertDontSee('login-stat-strip');
        $this->assertFileExists(public_path('favicon.svg'));
        $this->assertFileExists(public_path('images/agency-signature.svg'));
        $this->assertSame('#000000', Appearance::defaults()['login_panel']);
    }

    public function test_login_recalls_only_colors_and_authenticated_account_takes_precedence(): void
    {
        $colors = array_replace(Appearance::defaults(), ['page' => '#123456']);
        $this->withCookie('appearance_theme', json_encode($colors))->get('/login')->assertOk()->assertSee('--ap-page:#123456;', false);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('--ap-page:#ffffff;', false)->assertCookie('appearance_theme', json_encode(Appearance::defaults()));
        $this->withCookie('appearance_theme', json_encode(Appearance::defaults()))->patch(route('appearance.update'), ['colors' => $colors])->assertCookie('appearance_theme', json_encode($colors));
        $this->withCookie('appearance_theme', json_encode($colors))->delete(route('appearance.destroy'))->assertCookie('appearance_theme', json_encode(Appearance::defaults()));
    }

    public function test_malformed_login_theme_falls_back_to_safe_defaults(): void
    {
        foreach (['invalid json', '123', '{"page":"url(https://invalid.test)"}', '["#ffffff"]'] as $value) {
            $this->withCookie('appearance_theme', $value)->get('/login')->assertOk()->assertSee('--ap-page:#ffffff;', false)->assertDontSee('url(https://invalid.test)');
        }
    }
}
