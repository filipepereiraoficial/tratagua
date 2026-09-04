<?php
namespace App\Core;

/** Base dos controladores: renderização, redirecionamento e respostas JSON/CSV. */
abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        $data['flash']  = $data['flash']  ?? Flash::pull();
        $data['errors'] = $data['errors'] ?? Flash::errors();
        $data['old']    = $data['old']    ?? Flash::oldInput();
        $data['auth']   = Auth::user();
        $data['route']  = $this->request->path;
        View::render($template, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? null;
        if ($referer) {
            header('Location: ' . $referer);
            exit;
        }
        $this->redirect('/');
    }

    /** Rejeita um formulário preservando erros e valores digitados. */
    protected function rejectWith(Validator $validator, string $path): never
    {
        Flash::keepErrors($validator->errors());
        Flash::keepInput($this->request->post);
        Flash::error($validator->firstError() ?? 'Verifique os dados informados.');
        $this->redirect($path);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /**
     * Exporta linhas como CSV compatível com Excel pt-BR (BOM + ponto e vírgula).
     * @param array<int, array<string, scalar|null>> $rows
     */
    protected function csv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($out, array_map(
                static fn ($v) => is_float($v) ? number_format($v, 2, ',', '') : $v,
                array_values($row)
            ), ';', '"', '\\');
        }
        fclose($out);
        exit;
    }

    /**
     * Coleta os filtros aceitos pelos relatórios/gráficos a partir da query
     * string, descartando valores vazios.
     */
    protected function filters(array $keys = ['curso', 'turma', 'disciplina', 'assunto', 'aluno', 'avaliacao', 'tipo', 'dificuldade', 'inicio', 'fim', 'status_aluno']): array
    {
        $filters = [];
        foreach ($keys as $key) {
            $value = $this->request->query($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }
        return $filters;
    }

    /** Filtros da query já restringidos ao que o perfil pode enxergar. */
    protected function scopedFilters(array $keys = ['curso', 'turma', 'disciplina', 'assunto', 'aluno', 'avaliacao', 'tipo', 'dificuldade', 'inicio', 'fim', 'status_aluno']): array
    {
        return Scope::apply($this->filters($keys));
    }

    /** Bloqueia a ação quando o perfil não alcança o registro pedido. */
    protected function denyUnless(bool $permitido, string $mensagem = 'Você não tem acesso a este registro.'): void
    {
        if ($permitido) {
            return;
        }
        if ($this->request->wantsJson()) {
            $this->json(['error' => $mensagem], 403);
        }
        http_response_code(403);
        View::render('errors/403', ['title' => 'Acesso negado', 'message' => $mensagem], 'layouts/blank');
        exit;
    }

    protected function notFound(string $message = 'Registro não encontrado.'): never
    {
        http_response_code(404);
        if ($this->request->wantsJson()) {
            $this->json(['error' => $message], 404);
        }
        View::render('errors/404', ['title' => 'Não encontrado', 'message' => $message], 'layouts/blank');
        exit;
    }
}
