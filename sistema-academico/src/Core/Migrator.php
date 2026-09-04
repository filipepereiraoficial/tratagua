<?php
namespace App\Core;

/**
 * Aplica as migrações pendentes de database/migrations/.
 *
 * Um arquivo por dialeto: NNN_nome.{sqlite,mysql}.sql. O que já rodou fica
 * registrado na tabela `migrations`, então instalações existentes são
 * atualizadas sem perder dados e instalações novas apenas marcam tudo como
 * aplicado (o schema completo já contém as mudanças).
 */
class Migrator
{
    public static function ensureTable(): void
    {
        $sql = Database::driver() === 'mysql'
            ? 'CREATE TABLE IF NOT EXISTS migrations (
                 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                 migration VARCHAR(191) NOT NULL UNIQUE,
                 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : "CREATE TABLE IF NOT EXISTS migrations (
                 id INTEGER PRIMARY KEY AUTOINCREMENT,
                 migration TEXT NOT NULL UNIQUE,
                 applied_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
               )";
        Database::pdo()->exec($sql);
    }

    /** @return array<int, string> nomes das migrações do dialeto atual, em ordem */
    public static function available(): array
    {
        $sufixo = '.' . (Database::driver() === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
        $dir = APP_ROOT . '/database/migrations';
        if (!is_dir($dir)) {
            return [];
        }
        $nomes = [];
        foreach (scandir($dir) ?: [] as $arquivo) {
            if (str_ends_with($arquivo, $sufixo)) {
                $nomes[] = substr($arquivo, 0, -strlen($sufixo));
            }
        }
        sort($nomes);
        return $nomes;
    }

    public static function applied(): array
    {
        self::ensureTable();
        return array_column(Database::all('SELECT migration FROM migrations'), 'migration');
    }

    public static function pending(): array
    {
        $aplicadas = self::applied();
        return array_values(array_filter(self::available(), static fn ($m) => !in_array($m, $aplicadas, true)));
    }

    /**
     * Roda as migrações pendentes.
     * @return array<int, string> nomes das migrações aplicadas agora
     */
    public static function run(): array
    {
        self::ensureTable();
        $feitas = [];
        foreach (self::pending() as $nome) {
            foreach (self::statements($nome) as $comando) {
                try {
                    Database::pdo()->exec($comando);
                } catch (\PDOException $e) {
                    // Uma coluna que já existe não invalida a migração inteira:
                    // instalações parcialmente atualizadas continuam avançando.
                    if (!self::jaExiste($e)) {
                        throw $e;
                    }
                }
            }
            Database::insert('migrations', ['migration' => $nome]);
            $feitas[] = $nome;
        }
        return $feitas;
    }

    /** Marca tudo como aplicado sem executar — usado logo após criar o schema completo. */
    public static function markAllApplied(): void
    {
        self::ensureTable();
        foreach (self::pending() as $nome) {
            Database::insert('migrations', ['migration' => $nome]);
        }
    }

    private static function statements(string $nome): array
    {
        $sufixo = '.' . (Database::driver() === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
        $sql = file_get_contents(APP_ROOT . "/database/migrations/{$nome}{$sufixo}");
        if ($sql === false) {
            throw new \RuntimeException("Migração ilegível: {$nome}");
        }
        $linhas = [];
        foreach (preg_split('/\R/', $sql) ?: [] as $linha) {
            $limpa = trim($linha);
            if ($limpa !== '' && !str_starts_with($limpa, '--')) {
                $linhas[] = $linha;
            }
        }
        return array_values(array_filter(array_map('trim', explode(';', implode("\n", $linhas))), static fn ($c) => $c !== ''));
    }

    private static function jaExiste(\PDOException $e): bool
    {
        $mensagem = mb_strtolower($e->getMessage());
        foreach (['duplicate column', 'already exists', 'duplicate key name'] as $marca) {
            if (str_contains($mensagem, $marca)) {
                return true;
            }
        }
        return false;
    }
}
