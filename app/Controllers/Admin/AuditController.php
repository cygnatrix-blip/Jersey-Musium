<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;

final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $params = [];
        $where = '1 = 1';
        if ($q = $request->query('q')) {
            $where = '(l.action LIKE :q1 OR a.email LIKE :q2)';
            $params = ['q1' => "%$q%", 'q2' => "%$q%"];
        }
        return $this->admin('audit', [
            'title' => 'Audit log',
            'logs'  => Paginator::query(
                "SELECT l.*, a.name AS admin_name, a.email FROM audit_logs l LEFT JOIN admins a ON a.id = l.admin_id WHERE $where ORDER BY l.id DESC",
                $params,
                50
            ),
        ]);
    }
}
