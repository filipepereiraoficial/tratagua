<?php
namespace App\Core;

/**
 * Roteador simples: casa método + padrão de URI (com parâmetros {id}) e aplica
 * middlewares declarativos ("auth", "role:admin"). Toda escrita passa por CSRF.
 */
class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:array,middleware:array}> */
    private array $routes = [];
    private array $groupMiddleware = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function any(array $methods, string $pattern, array $handler, array $middleware = []): void
    {
        foreach ($methods as $method) {
            $this->add($method, $pattern, $handler, $middleware);
        }
    }

    /** Aplica um conjunto de middlewares a todas as rotas declaradas no callback. */
    public function group(array $middleware, callable $callback): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($previous, $middleware);
        $callback($this);
        $this->groupMiddleware = $previous;
    }

    private function add(string $method, string $pattern, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): void
    {
        $pathMatched = false;

        foreach ($this->routes as $route) {
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#i', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            foreach ($route['middleware'] as $middleware) {
                Middleware::handle($middleware, $request);
            }

            if ($request->isPost() && !Csrf::check($request->input('_token'))) {
                Middleware::abortCsrf($request);
            }

            [$class, $method] = $route['handler'];
            $controller = new $class($request);
            $controller->{$method}(...array_values($params));
            return;
        }

        http_response_code($pathMatched ? 405 : 404);
        if ($request->wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $pathMatched ? 'Método não permitido' : 'Recurso não encontrado']);
            return;
        }
        View::render('errors/404', ['title' => 'Página não encontrada'], 'layouts/blank');
    }
}
