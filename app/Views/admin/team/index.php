<?php /** @var array $admins @var array $roles */ ?>
<div class="page-head"><div><h1>Team &amp; roles</h1><p>Give each staff member only the access they need.</p></div>
    <div class="flex gap"><a class="btn btn--outline" href="/admin/team/roles/create">+ New role</a><a class="btn" href="/admin/team/admins/create">+ Add admin</a></div></div>
<div class="cols cols--2-1">
    <section class="card">
        <h2>Admins</h2>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td><a class="link" href="/admin/team/admins/<?= (int) $a['id'] ?>/edit"><?= e($a['name']) ?></a><br><span class="muted"><?= e($a['email']) ?></span></td>
                    <td><?= e($a['role_name']) ?></td>
                    <td><?= $a['last_login_at'] ? e(format_date($a['last_login_at'], 'd M Y, h:i A')) . '<br><span class="muted">' . e($a['last_login_ip']) . '</span>' : '—' ?></td>
                    <td><?= $a['status'] === 'active' ? '<span class="badge badge--success">Active</span>' : '<span class="badge badge--muted">Disabled</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
    <section class="card">
        <h2>Roles</h2>
        <div class="table-wrap"><table class="table">
            <tbody>
            <?php foreach ($roles as $r): $n = count(json_decode($r['permissions'], true) ?: []); ?>
                <tr><td><a class="link" href="/admin/team/roles/<?= (int) $r['id'] ?>/edit"><?= e($r['name']) ?></a><br><span class="muted"><?= $r['slug'] === 'super_admin' ? 'All permissions' : $n . ' permissions' ?></span></td><td class="num"><?= (int) $r['members'] ?> member(s)</td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</div>
