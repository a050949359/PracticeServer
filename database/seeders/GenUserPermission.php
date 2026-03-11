<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Team;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class GenUserPermission extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清除權限快取
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 建立權限
        $permissions = [
            'user.view',
            'user.create',
            'user.update',
            'user.delete',
            'user.view.all',
            'user.manage.team',
            'user.manage.all',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // 建立團隊
        $defaultTeam = Team::create(['name' => 'Default']);
        $firstTeam = Team::create(['name' => 'First Team']);
        $visitorTeam = Team::create(['name' => 'Visitor Team']);

        // 建立角色並分配權限
        // Admin 角色 (Default Team)
        $adminRole = Role::create(['name' => 'admin', 'team_id' => $defaultTeam->id, 'is_leader' => true]);
        $adminRole->givePermissionTo(Permission::all());

        // Leader 角色 (First Team)
        $T1leaderRole = Role::create(['name' => 'leader', 'team_id' => $firstTeam->id, 'is_leader' => true]);
        $T1leaderRole->givePermissionTo([
            'user.view',
            'user.create', 
            'user.update',
            'user.delete',
            'user.manage.team',
        ]);

        // Member 角色 (First Team)
        $T1memberRole = Role::create(['name' => 'member', 'team_id' => $firstTeam->id, 'is_leader' => false]);
        $T1memberRole->givePermissionTo([
            'user.view',
            'user.create',
            'user.update',
            'user.delete'
        ]);
        
        // Visitor 角色 (Visitor Team)
        $visitorRole = Role::create(['name' => 'visitor', 'team_id' => $visitorTeam->id, 'is_leader' => false]);
        $visitorRole->givePermissionTo(['user.view']);

        // 建立測試使用者並分配團隊和角色
        // Admin 使用者
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'a050949359@gmail.com',
            'password' => bcrypt('741105'),
        ]);
        setPermissionsTeamId($defaultTeam); // 設定權限的 team_id 為第一個團隊
        $admin->assignRole($adminRole);

        // Leader 使用者  
        $leader = User::create([
            'name' => 'Leader',
            'email' => 'leader@example.com',
            'password' => bcrypt('password'),
        ]);
        setPermissionsTeamId($firstTeam); // 設定權限的 team_id 為第一個團隊
        $leader->assignRole($T1leaderRole);

        // Member 使用者（屬於兩個團隊）
        $member = User::create([
            'name' => 'Member',
            'email' => 'editor@example.com', 
            'password' => bcrypt('password'),
        ]);
        setPermissionsTeamId($firstTeam); // 設定權限的 team_id 為第一個團隊
        $member->assignRole($T1memberRole);

        // Visitor 使用者
        $visitor = User::create([
            'name' => 'Visitor',
            'email' => 'viewer@example.com',
            'password' => bcrypt('password'),
        ]);
        setPermissionsTeamId($visitorTeam); // 設定team_id
        $visitor->assignRole($visitorRole);
    }
}
