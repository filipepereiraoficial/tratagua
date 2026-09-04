<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Validator;
use App\Models\Setting;

/**
 * Instalador web: cria o schema e a carga inicial (conta administradora,
 * curso, disciplina e turma) descritos nos itens 21 e 22 do escopo.
 * Fica indisponível assim que a tabela `users` existe.
 */
class InstallController extends Controller
{
    public function show(): void
    {
        if (Database::tableExists('users')) {
            $this->redirect('/login');
        }
        $this->view('auth/install', [
            'title'  => 'Instalação',
            'driver' => Database::driver(),
        ], 'layouts/auth');
    }

    public function run(): void
    {
        if (Database::tableExists('users')) {
            Flash::warning('O sistema já está instalado.');
            $this->redirect('/login');
        }

        $validator = Validator::make($this->request->post, [
            'name'     => 'required|max:150',
            'email'    => 'required|email|max:150',
            'password' => 'required|min:8|confirmed',
        ], [
            'name'     => 'nome',
            'email'    => 'e-mail',
            'password' => 'senha',
        ]);

        if ($validator->fails()) {
            $this->rejectWith($validator, '/instalar');
        }

        $schemaFile = APP_ROOT . '/database/schema.' . (Database::driver() === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
        $sql = file_get_contents($schemaFile);
        if ($sql === false) {
            Flash::error('Não foi possível ler o arquivo de schema.');
            $this->redirect('/instalar');
        }

        foreach (self::splitStatements($sql) as $statement) {
            Database::pdo()->exec($statement);
        }

        require_once APP_ROOT . '/database/seed.php';
        $result = \painel_seed([
            'name'     => $this->request->input('name'),
            'email'    => $this->request->input('email'),
            'password' => $this->request->input('password'),
        ], (bool) $this->request->input('demo'));

        Setting::resetToDefaults();

        Flash::success('Sistema instalado. ' . $result['message']);
        $this->redirect('/login');
    }

    /** Divide o arquivo SQL em comandos, ignorando comentários. */
    private static function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $clean[] = $line;
        }
        $statements = explode(';', implode("\n", $clean));
        return array_values(array_filter(array_map('trim', $statements), static fn ($s) => $s !== ''));
    }
}
