<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;

/** Admin users & roles (RBAC). */
final class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->admin('team/index', [
            'title'  => 'Team & roles',
            'admins' => db()->select('SELECT a.*, r.name AS role_name FROM admins a JOIN roles r ON r.id = a.role_id ORDER BY a.id'),
            'roles'  => db()->select('SELECT r.*, (SELECT COUNT(*) FROM admins a WHERE a.role_id = r.id) AS members FROM roles r ORDER BY r.id'),
        ]);
    }

    public function createAdmin(Request $request): Response
    {
        return $this->admin('team/admin-form', ['title' => 'Invite admin', 'a' => null, 'roles' => db()->select('SELECT * FROM roles ORDER BY name')]);
    }

    public function editAdmin(Request $request, string $id): Response
    {
        $a = db()->first('SELECT * FROM admins WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        return $this->admin('team/admin-form', ['title' => 'Edit admin', 'a' => $a, 'roles' => db()->select('SELECT * FROM roles ORDER BY name')]);
    }

    public function storeAdmin(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'     => 'required|max:120',
            'email'    => 'required|email|unique:admins,email',
            'role_id'  => 'required|integer|exists:roles,id',
            'password' => 'required|min:12|max:200|confirmed',
        ]);
        $this->guardRoleGrant((int) $data['role_id']);
        $id = db()->insert('admins', [
            'name' => $data['name'], 'email' => strtolower($data['email']), 'role_id' => (int) $data['role_id'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
        AuditService::log('admin.create', 'admin', $id, ['email' => $data['email']]);
        return redirect('/admin/team')->with('success', 'Admin user created.');
    }

    public function updateAdmin(Request $request, string $id): Response
    {
        $id = (int) $id;
        $data = $this->validate($request, [
            'name'     => 'required|max:120',
            'email'    => "required|email|unique:admins,email,$id",
            'role_id'  => 'required|integer|exists:roles,id',
            'status'   => 'required|in:active,disabled',
            'password' => 'nullable|min:12|max:200|confirmed',
        ]);
        if ($id === admin_auth()->id() && $data['status'] === 'disabled') {
            throw new ValidationException(['status' => ['You cannot disable your own account.']]);
        }
        $target = db()->first('SELECT a.*, r.slug AS role_slug FROM admins a JOIN roles r ON r.id = a.role_id WHERE a.id = :id', ['id' => $id])
            ?? throw new HttpException(404);
        // Only a Super Admin may modify a Super Admin account or grant the Super Admin role.
        if ($target['role_slug'] === 'super_admin' && admin_auth()->user()['role_slug'] !== 'super_admin') {
            throw new HttpException(403, 'Only a Super Admin can modify a Super Admin account.');
        }
        $this->guardRoleGrant((int) $data['role_id']);
        if ($id === admin_auth()->id() && (int) $data['role_id'] !== (int) $target['role_id']) {
            throw new ValidationException(['role_id' => ['You cannot change your own role.']]);
        }
        // Never leave the store without an active Super Admin.
        $newSlug = (string) db()->scalar('SELECT slug FROM roles WHERE id = :r', ['r' => (int) $data['role_id']]);
        if ($target['role_slug'] === 'super_admin' && ($newSlug !== 'super_admin' || $data['status'] === 'disabled')) {
            $others = (int) db()->scalar(
                "SELECT COUNT(*) FROM admins a JOIN roles r ON r.id = a.role_id WHERE r.slug = 'super_admin' AND a.status = 'active' AND a.id <> :id",
                ['id' => $id]
            );
            if ($others === 0) {
                throw new ValidationException(['role_id' => ['This is the last active Super Admin – promote someone else first.']]);
            }
        }
        $update = ['name' => $data['name'], 'email' => strtolower($data['email']), 'role_id' => (int) $data['role_id'], 'status' => $data['status']];
        if ($data['password']) {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        db()->update('admins', $update, 'id = :id', ['id' => $id]);
        AuditService::log('admin.update', 'admin', $id, ['role' => $data['role_id'], 'status' => $data['status'], 'password_changed' => (bool) $data['password']]);
        return redirect('/admin/team')->with('success', 'Admin updated.');
    }

    public function createRole(Request $request): Response
    {
        return $this->admin('team/role-form', ['title' => 'New role', 'r' => null, 'perms' => config('permissions')]);
    }

    public function editRole(Request $request, string $id): Response
    {
        $r = db()->first('SELECT * FROM roles WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        $r['permissions'] = json_decode($r['permissions'], true) ?: [];
        return $this->admin('team/role-form', ['title' => 'Edit role', 'r' => $r, 'perms' => config('permissions')]);
    }

    public function storeRole(Request $request): Response
    {
        $data = $this->roleData($request, null);
        $id = db()->insert('roles', $data);
        AuditService::log('role.create', 'role', $id, ['name' => $data['name']]);
        return redirect('/admin/team')->with('success', 'Role created.');
    }

    public function updateRole(Request $request, string $id): Response
    {
        $r = db()->first('SELECT * FROM roles WHERE id = :id', ['id' => (int) $id]) ?? throw new HttpException(404);
        if ($r['slug'] === 'super_admin') {
            return back()->with('error', 'The Super Admin role always has every permission and cannot be edited.');
        }
        $me = admin_auth()->user();
        if ($me['role_slug'] !== 'super_admin' && (int) db()->scalar('SELECT role_id FROM admins WHERE id = :id', ['id' => $me['id']]) === (int) $id) {
            throw new HttpException(403, 'You cannot change the permissions of your own role.');
        }
        $data = $this->roleData($request, (int) $id);
        db()->update('roles', $data, 'id = :id', ['id' => (int) $id]);
        AuditService::log('role.update', 'role', (int) $id, ['permissions' => json_decode($data['permissions'])]);
        return redirect('/admin/team')->with('success', 'Role saved.');
    }

    /** Prevent privilege escalation: only Super Admins can hand out the Super Admin role. */
    private function guardRoleGrant(int $roleId): void
    {
        $slug = db()->scalar('SELECT slug FROM roles WHERE id = :r', ['r' => $roleId]);
        if ($slug === 'super_admin' && admin_auth()->user()['role_slug'] !== 'super_admin') {
            throw new HttpException(403, 'Only a Super Admin can grant the Super Admin role.');
        }
    }

    private function roleData(Request $request, ?int $id): array
    {
        $data = $this->validate($request, [
            'name' => 'required|max:80',
            'slug' => 'required|slug|max:80|unique:roles,slug' . ($id ? ",$id" : ''),
        ]);
        $all = array_keys(config('permissions'));
        $perms = array_values(array_intersect($all, (array) $request->input('permissions', [])));
        return ['name' => $data['name'], 'slug' => str_replace('-', '_', $data['slug']), 'permissions' => json_encode($perms)];
    }
}
