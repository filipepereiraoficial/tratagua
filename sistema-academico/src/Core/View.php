<?php
namespace App\Core;

/** Renderiza templates PHP dentro de um layout. */
class View
{
    private static string $path = '';

    public static function setPath(string $path): void
    {
        self::$path = rtrim($path, '/');
    }

    public static function render(string $template, array $data = [], string $layout = 'layouts/app'): void
    {
        echo self::capture($template, $data, $layout);
    }

    public static function capture(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    /** Renderiza um template isolado (sem layout) e devolve o HTML. */
    public static function partial(string $template, array $data = []): string
    {
        $file = self::$path . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template não encontrado: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}
