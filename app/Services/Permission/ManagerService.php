<?php

namespace App\Services\Permission;

use App\Models\Team;
use App\Models\User;
use App\Repository\Permission\PermissionRepository;
use App\Repository\Permission\RoleRepository;
use App\Repository\Permission\TeamRepository;
use App\Repository\Permission\UserRepository;
use App\Services\Service;
use Illuminate\Support\Facades\DB;
use Throwable;

class ManagerService extends Service
{
    public function __construct(
        private TeamRepository $teamRepository,
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
        private UserRepository $userRepository,
    ) {}

    /**
     * 取得所有團隊列表: 角色, 成員
     */
    public function getAllTeams(bool $needMembers = true): self
    {
        try {
            $teams = $needMembers ? $this->teamRepository->getAllTeamsMembers() : $this->teamRepository->getAllTeams();
            $this->generateResponse($teams);
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 取得單一團隊資料: 角色, 成員
     */
    public function getTeam(int $teamId, bool $needMembers = true): self
    {
        try {
            $team = $needMembers ? $this->teamRepository->getTeamMembers($teamId) : $this->teamRepository->getTeamData($teamId);
            $this->generateResponse($team);
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 新增團隊
     * 'data' => [
     *    'team' => [
     *       'name' => 'Team A',
     *    'role' => [
     *       'name' => 'Role A',
     *       'is_leader' => true,
     *    ]
     * ];
     */
    public function createTeam(array $teamData): self
    {
        try {
            $team = DB::transaction(function () use ($teamData): Team {
                $team = $this->teamRepository->createTeam([
                    'name' => $teamData['team']['name'],
                ]);

                if (isset($teamData['role']) && is_array($teamData['role'])) {
                    $this->roleRepository->createRole($team->id, [
                        'name' => $teamData['role']['name'] ?? null,
                        'is_leader' => $teamData['role']['is_leader'] ?? false,
                    ]);
                }

                return $team->load('roles');
            });

            $this->generateResponse($team, 'Team created successfully', 201);
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 編輯團隊
     */
    public function updateTeam(int $teamId, array $teamData): self
    {
        try {
            $this->teamRepository->updateTeam($teamId, $teamData);

            $this->generateResponse();
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 移除團隊
     */
    public function deleteTeam(int $teamId): self
    {
        try {
            $this->teamRepository->deleteTeam($teamId);
            $this->generateResponse(null, 'Team deleted successfully');
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 取得團隊角色列表
     */
    public function getTeamRoles(int $teamId): self
    {
        try {
            $team = $this->teamRepository->getTeamRoles($teamId);
            $this->generateResponse($team);
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 新增團隊角色
     */
    public function createTeamRole(int $teamId, array $roleData): self
    {
        try {
            $role = $this->roleRepository->createRole($teamId, $roleData);
            $this->generateResponse($role->load(['permissions', 'users', 'team']), 'Role created successfully', 201);
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 編輯團隊角色
     */
    public function updateTeamRole(int $roleId, array $roleData): self
    {
        try {
            $this->roleRepository->updateRole($roleId, $roleData);
            $this->generateResponse();
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 移除團隊角色
     */
    public function deleteTeamRole(int $roleId): self
    {
        try {
            $this->roleRepository->deleteRole($roleId);
            $this->generateResponse(null, 'Role deleted successfully');
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 取得團隊角色權限
     */
    public function getTeamRolePermissions(int $roleId): self
    {
        try {
            $role = $this->roleRepository->getRoleData($roleId);
            if (! $role) {
                throw new \DomainException('Role not found');
            }

            $this->generateResponse([
                'role' => $role,
                'permissions' => $role->permissions,
            ]);
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 編輯團隊角色權限
     */
    public function updateTeamRolePermissions(int $roleId, array $permissionIds): self
    {
        try {
            $this->permissionRepository->getPermissionByIds($permissionIds);
            $this->roleRepository->assignPermissions($roleId, $permissionIds);

            $role = $this->roleRepository->getRoleData($roleId);
            if (! $role) {
                throw new \DomainException('Role not found');
            }

            $this->generateResponse([
                'role' => $role,
                'permissions' => $role->permissions,
            ], 'Role permissions updated successfully');
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 新增團隊成員
     */
    public function addTeamMember(array $userData, int $teamId, int $roleId): self
    {
        try {
            $this->userRepository->createUserWithTeamAndRole($userData, $teamId, $roleId);
            $this->generateResponse();
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    /**
     * 移除團隊成員
     */
    public function removeTeamMember(int $userId, int $teamId): self
    {
        try {
            $this->userRepository->removeUserFromTeam($userId, $teamId);
            $this->generateResponse();
        } catch (Throwable $throwable) {
            $this->respondWithThrowable($throwable);
        }

        return $this;
    }

    private function respondWithThrowable(Throwable $throwable): void
    {
        $this->generateResponse(null, $throwable->getMessage(), $this->resolveStatusCode($throwable));
    }

    private function resolveStatusCode(Throwable $throwable): int
    {
        $code = $throwable->getCode();
        if (is_int($code) && $code >= 400 && $code < 600) {
            return $code;
        }

        $message = strtolower($throwable->getMessage());

        if (str_contains($message, 'validation')) {
            return 422;
        }

        if (str_contains($message, 'not found')) {
            return 404;
        }

        if (str_contains($message, 'already') || str_contains($message, 'cannot') || str_contains($message, 'not a member')) {
            return 409;
        }

        return 500;
    }
}
