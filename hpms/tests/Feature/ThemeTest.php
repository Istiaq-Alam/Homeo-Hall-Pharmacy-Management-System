<?php

namespace Tests\Feature;

use App\Models\Chamber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_authenticated_roles_can_open_the_themes_page(): void
    {
        $chamber = Chamber::query()->create(['name' => 'Theme Chamber']);
        $users = [
            User::factory()->admin()->create(),
            User::factory()->owner()->create(['chamber_id' => $chamber->id]),
            User::factory()->staff()->create(['chamber_id' => $chamber->id]),
        ];

        foreach ($users as $user) {
            $response = $this->actingAs($user)->get(route('themes.index'));
            $response->assertOk()
                ->assertSee('Themes')
                ->assertSee('data-theme="'.$user->activeTheme().'"', false)
                ->assertSee('Starbucks');

            $html = $response->getContent();
            $settingsPosition = strpos($html, '>Settings</a>');
            $themesPosition = strpos($html, '>Themes</a>');
            $this->assertNotFalse($settingsPosition);
            $this->assertNotFalse($themesPosition);
            $this->assertLessThan($themesPosition, $settingsPosition);
        }
    }

    public function test_theme_selection_is_saved_only_to_the_current_user(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();

        $this->actingAs($first)->post(route('themes.update'), ['theme' => 'starbucks'])
            ->assertRedirect(route('themes.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('starbucks', $first->fresh()->theme);
        $this->assertSame('apple', $second->fresh()->activeTheme());
        $this->actingAs($first)->get(route('themes.index'))
            ->assertOk()
            ->assertSee('Starbucks')
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_unknown_theme_choices_are_rejected_without_changing_the_preference(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post(route('themes.update'), ['theme' => 'unknown'])
            ->assertSessionHasErrors('theme');

        $this->assertSame('apple', $user->fresh()->activeTheme());
    }
}
