<?php
namespace App\Helpers;

/**
 * Simple Router - Maps URLs to Controllers
 */
class Router
{
    private string $baseUrl;
    private array $routes = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function get(string $path, string $controller, string $method): void
    {
        $this->addRoute('GET', $path, $controller, $method);
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->addRoute('POST', $path, $controller, $method);
    }

    private function addRoute(string $httpMethod, string $path, string $controller, string $method): void
    {
        $this->routes[] = [
            'httpMethod'  => $httpMethod,
            'path'        => $path,
            'controller'  => $controller,
            'method'      => $method,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $url = $_GET['url'] ?? '';
        $url = '/' . trim($url, '/');

        foreach ($this->routes as $route) {
            if ($route['httpMethod'] !== $requestMethod) {
                continue;
            }

            $pattern = $this->convertToRegex($route['path']);
            
            if (preg_match($pattern, $url, $matches)) {
                $controllerClass = "App\\Controllers\\{$route['controller']}";
                $method = $route['method'];

                if (!class_exists($controllerClass)) {
                    $this->sendError(500, "Controller {$controllerClass} not found");
                    return;
                }

                $controller = new $controllerClass();
                
                if (!method_exists($controller, $method)) {
                    $this->sendError(500, "Method {$method} not found in {$controllerClass}");
                    return;
                }

                // Extract named parameters and convert to positional to avoid PHP 8 named argument issues
                $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                
                // Decode HashIds back to integers
                foreach ($params as &$param) {
                    $decoded = \App\Helpers\HashId::decode($param);
                    if ($decoded !== null) {
                        $param = (string)$decoded;
                    }
                }
                
                call_user_func_array([$controller, $method], $params);
                return;
            }
        }

        $this->sendError(404, 'Page not found');
    }

    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        if ($code === 404) {
            require APP_PATH . '/Views/errors/404.php';
        } else {
            echo "<h1>Error {$code}</h1><p>{$message}</p>";
        }
    }
}
