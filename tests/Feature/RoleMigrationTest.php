<?php
namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\{AuditLog, Client, User};
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        /** Schema changes require a separate in-memory connection without the suite's wrapping transaction. */
        $this->artisan('migrate:fresh', ['--force'=>true])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }

    public function test_migration_preserves_accounts_assignments_and_audits_previous_roles(): void
    {
        $admin=User::factory()->create(['role'=>UserRole::Admin]);
        $operator=User::factory()->create(['role'=>UserRole::Operator]);
        $client=Client::factory()->create();
        $migration=require database_path('migrations/2026_10_19_210000_replace_legacy_user_roles.php');
        $migration->down();
        DB::table('users')->where('id',$operator->id)->update(['role'=>'commercial','remember_token'=>'old-token']);
        $client->update(['commercial_user_id'=>$operator->id]);
        $client->authorizedUsers()->attach($operator);
        DB::table('sessions')->insert(['id'=>'legacy-session','user_id'=>$operator->id,'payload'=>'','last_activity'=>time()]);
        $migration->up();
        $this->assertSame(UserRole::Operator,$operator->fresh()->role);
        $this->assertSame(UserRole::Admin,$admin->fresh()->role);
        $this->assertSame($operator->id,$client->fresh()->commercial_user_id);
        $this->assertNull($operator->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions',['id'=>'legacy-session']);
        $this->assertDatabaseHas('client_user',['client_id'=>$client->id,'user_id'=>$operator->id]);
        $log=AuditLog::where('action','roles_migrated')->sole();
        $this->assertSame('commercial',$log->old_values['role']);
        $this->assertSame('operator',$log->new_values['role']);
    }
}
