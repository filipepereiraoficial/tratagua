<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;

class SettingsController extends Controller
{
    /** Faixas numéricas aceitas por cada parâmetro (validação de sanidade). */
    private const RANGES = [
        'faixa_dominio'           => [1, 100],
        'faixa_intermediario'     => [0, 99],
        'peso_desempenho'         => [0, 1],
        'peso_evolucao'           => [0, 1],
        'peso_frequencia'         => [0, 1],
        'peso_consistencia'       => [0, 1],
        'id_evolucao'             => [1, 100],
        'id_atencao'              => [0, 99],
        'min_questoes_assunto'    => [1, 100],
        'min_avaliacoes_evolucao' => [2, 50],
        'min_avaliacoes_indice'   => [1, 50],
        'janela_recente'          => [1, 20],
        'fator_evolucao'          => [0.1, 50],
        'fator_consistencia'      => [0.1, 20],
        'frequencia_minima'       => [0, 100],
        'media_alerta'            => [0, 100],
        'queda_alerta'            => [0.1, 100],
        'evolucao_alerta'         => [0.1, 100],
        'limite_dificuldade'      => [0, 100],
        'ocorrencias_persistente' => [2, 20],
    ];

    public function index(): void
    {
        $this->view('settings/index', [
            'title'      => 'Configurações',
            'valores'    => Setting::all(),
            'padroes'    => Setting::DEFAULTS,
            'pesos'      => Setting::weights(),
        ]);
    }

    public function update(): void
    {
        $entrada   = $this->request->post;
        $validator = new Validator($entrada);

        foreach (self::RANGES as $chave => [$min, $max]) {
            if (!array_key_exists($chave, $entrada)) {
                continue;
            }
            $valor = str_replace(',', '.', (string) $entrada[$chave]);
            if (!is_numeric($valor)) {
                $validator->add($chave, "O parâmetro {$chave} deve ser numérico.");
                continue;
            }
            if ((float) $valor < $min || (float) $valor > $max) {
                $validator->add($chave, "O parâmetro {$chave} deve estar entre {$min} e {$max}.");
            }
            $entrada[$chave] = $valor;
        }

        // Coerências entre faixas.
        if (isset($entrada['faixa_dominio'], $entrada['faixa_intermediario'])
            && (float) $entrada['faixa_intermediario'] >= (float) $entrada['faixa_dominio']) {
            $validator->add('faixa_intermediario', 'A faixa intermediária deve ser menor que a faixa de domínio.');
        }
        if (isset($entrada['id_evolucao'], $entrada['id_atencao'])
            && (float) $entrada['id_atencao'] >= (float) $entrada['id_evolucao']) {
            $validator->add('id_atencao', 'O corte de atenção deve ser menor que o corte de evolução.');
        }

        $somaPesos = 0.0;
        foreach (['peso_desempenho', 'peso_evolucao', 'peso_frequencia', 'peso_consistencia'] as $peso) {
            $somaPesos += (float) ($entrada[$peso] ?? 0);
        }
        if ($somaPesos <= 0) {
            $validator->add('peso_desempenho', 'A soma dos pesos do Índice de Desenvolvimento deve ser maior que zero.');
        }

        if ($validator->fails()) {
            $this->rejectWith($validator, '/configuracoes');
        }

        $entrada['justificada_conta'] = !empty($entrada['justificada_conta']) ? '1' : '0';
        Setting::putMany($entrada);

        if (abs($somaPesos - 1.0) > 0.001) {
            Flash::info('Os pesos somavam ' . num($somaPesos, 2) . '; eles são normalizados automaticamente para somar 1.');
        }
        Flash::success('Configurações salvas. Indicadores e classificações já refletem os novos critérios.');
        $this->redirect('/configuracoes');
    }

    public function reset(): void
    {
        Setting::resetToDefaults();
        Flash::success('Configurações restauradas para os valores padrão.');
        $this->redirect('/configuracoes');
    }

    // -------------------------------------------------------------- usuários

    public function users(): void
    {
        $this->view('settings/users', [
            'title'    => 'Usuários',
            'usuarios' => User::all(),
            'alunos'   => Student::search([]),
        ]);
    }

    public function storeUser(): void
    {
        $validator = Validator::make($this->request->post, [
            'name'     => 'required|max:150',
            'email'    => 'required|email|max:150|unique:users,email',
            'password' => 'required|min:8',
            'role'     => 'required|in:admin,professor,aluno',
        ], ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'role' => 'perfil']);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/configuracoes/usuarios');
        }

        User::create(array_merge($this->request->post, ['must_change_password' => 1]));
        Flash::success('Usuário criado. Ele deverá definir uma nova senha no primeiro acesso.');
        $this->redirect('/configuracoes/usuarios');
    }

    public function updateUser(string $id): void
    {
        $userId = (int) $id;
        if (!User::find($userId)) {
            $this->notFound('Usuário não encontrado.');
        }

        $validator = Validator::make($this->request->post, [
            'name'  => 'required|max:150',
            'email' => 'required|email|max:150|unique:users,email,' . $userId,
            'role'  => 'required|in:admin,professor,aluno',
        ], ['name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil']);

        $ativo = !empty($this->request->input('is_active'));
        if ($userId === Auth::id() && (!$ativo || $this->request->input('role') !== 'admin')) {
            $validator->add('role', 'Você não pode remover o próprio acesso de administrador nem se desativar.');
        }
        if ($this->request->input('role') !== 'admin' && self::isLastAdmin($userId)) {
            $validator->add('role', 'É necessário manter ao menos um administrador ativo.');
        }

        if ($validator->fails()) {
            $this->rejectWith($validator, '/configuracoes/usuarios');
        }

        User::update($userId, [
            'name'       => $this->request->input('name'),
            'email'      => $this->request->input('email'),
            'role'       => $this->request->input('role'),
            'is_active'  => $ativo ? 1 : 0,
            'student_id' => (int) $this->request->input('student_id', 0) ?: null,
        ]);
        Flash::success('Usuário atualizado.');
        $this->redirect('/configuracoes/usuarios');
    }

    public function resetUserPassword(string $id): void
    {
        $userId = (int) $id;
        if (!User::find($userId)) {
            $this->notFound('Usuário não encontrado.');
        }
        $senha = (string) $this->request->input('password', '');
        if (mb_strlen($senha) < 8) {
            Flash::error('A nova senha deve ter ao menos 8 caracteres.');
            $this->redirect('/configuracoes/usuarios');
        }
        User::updatePassword($userId, $senha, true);
        Flash::success('Senha redefinida. O usuário deverá trocá-la no próximo acesso.');
        $this->redirect('/configuracoes/usuarios');
    }

    public function destroyUser(string $id): void
    {
        $userId = (int) $id;
        if ($userId === Auth::id()) {
            Flash::error('Você não pode excluir o próprio usuário.');
            $this->redirect('/configuracoes/usuarios');
        }
        if (self::isLastAdmin($userId)) {
            Flash::error('É necessário manter ao menos um administrador ativo.');
            $this->redirect('/configuracoes/usuarios');
        }
        User::delete($userId);
        Flash::success('Usuário excluído.');
        $this->redirect('/configuracoes/usuarios');
    }

    private static function isLastAdmin(int $userId): bool
    {
        $user = User::find($userId);
        if (!$user || $user['role'] !== 'admin') {
            return false;
        }
        $admins = (int) \App\Core\Database::value(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1", [], 0
        );
        return $admins <= 1;
    }
}
