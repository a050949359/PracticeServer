<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreateRole extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $team = Team::create(['name' => 'Default']);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::create(['name' => 'user.view']);
        Permission::create(['name' => 'user.create']);
        Permission::create(['name' => 'user.update']);
        Permission::create(['name' => 'user.delete']);
        Permission::create(['name' => 'user.view.all']);
        Permission::create(['name' => 'user.manage.all']);
        $role = Role::create(['name' => 'admin', 'team_id' => $team->id]);
        $role->givePermissionTo(Permission::all());

        $team1 = Team::create(['name' => 'First Team']);

        $role = Role::create(['name' => 'manager', 'team_id' => $team1->id]);
        $role->givePermissionTo(Permission::findByName('user.view'));
        $role->givePermissionTo(Permission::findByName('user.create'));
        $role->givePermissionTo(Permission::findByName('user.update'));
        $role->givePermissionTo(Permission::findByName('user.delete'));
        $role->givePermissionTo(Permission::findByName('user.view.all'));
        $role->givePermissionTo(Permission::findByName('user.manage.all'));

        $role = Role::create(['name' => 'editor', 'team_id' => $team1->id]);
        $role->givePermissionTo(Permission::findByName('user.view'));
        $role->givePermissionTo(Permission::findByName('user.create'));
        $role->givePermissionTo(Permission::findByName('user.update'));
        $role->givePermissionTo(Permission::findByName('user.delete'));
        
        $role = Role::create(['name' => 'viewer', 'team_id' => $team1->id]);
        $role->givePermissionTo(Permission::findByName('user.view'));
    }
}
