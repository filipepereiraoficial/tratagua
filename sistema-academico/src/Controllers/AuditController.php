<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\User;

/** Trilha de auditoria — quem alterou o quê. Somente administrador. */
class AuditController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters(['usuario', 'entidade', 'acao', 'inicio', 'fim']);
        $this->view('admin/audit', [
            'title'     => 'Auditoria',
            'registros' => ActivityLog::search($filters, 300),
            'total'     => ActivityLog::total(),
            'filters'   => $filters,
            'usuarios'  => User::all(),
            'entidades' => ActivityLog::entities(),
            'acoes'     => ActivityLog::actions(),
        ]);
    }
}
