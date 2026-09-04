<?php
namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Camada única de acesso ao banco. Todo SQL do sistema passa por aqui, sempre
 * com prepared statements — nenhum valor de usuário é concatenado em SQL.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';

    public static function connect(array $config): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$driver = $config['driver'] ?? 'sqlite';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if (self::$driver === 'mysql') {
                $c = $config['mysql'];
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $c['host'], (int) $c['port'], $c['database'], $c['charset'] ?? 'utf8mb4'
                );
                self::$pdo = new PDO($dsn, $c['username'], $c['password'], $options);
            } else {
                $path = $config['sqlite']['path'];
                $dir  = dirname($path);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA journal_mode = WAL');
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Não foi possível conectar ao banco de dados: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Banco de dados não inicializado.');
        }
        return self::$pdo;
    }

    public static function driver(): string
    {
        return self::$driver;
    }

    public static function isConnected(): bool
    {
        return self::$pdo instanceof PDO;
    }

    /** Executa um statement preparado e devolve o PDOStatement. */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Primeira coluna da primeira linha (contagens, somas, existência). */
    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }

    /** Insere um registro e devolve o id gerado. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $cols),
            implode(', ', array_map(static fn ($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** Atualiza registros filtrados por uma cláusula WHERE parametrizada. */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[] = "$col = :set_$col";
            $params["set_$col"] = $val;
        }
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::run("DELETE FROM $table WHERE $where", $params)->rowCount();
    }

    /** Executa um callback dentro de uma transação, com rollback em caso de erro. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Concatena strings de forma portátil entre MySQL e SQLite. */
    public static function concat(string ...$parts): string
    {
        if (self::$driver === 'mysql') {
            return 'CONCAT(' . implode(', ', $parts) . ')';
        }
        return implode(' || ', $parts);
    }

    public static function tableExists(string $table): bool
    {
        try {
            if (self::$driver === 'mysql') {
                return (bool) self::value(
                    'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                    [$table]
                );
            }
            return (bool) self::value(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
