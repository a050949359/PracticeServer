<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class GenSuperUserPermission extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 清除權限快取
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = Role::all();

        // 建立超級使用者並分配團隊和角色
        // SuperUser create
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('741105'),
        ]);

        foreach ($roles as $role) {
            if ($role->is_leader) {
                setPermissionsTeamId($role->team_id); // 設定權限的 team_id 為角色所屬團隊
                $admin->assignRole($role);
            }
        }
    }
}
