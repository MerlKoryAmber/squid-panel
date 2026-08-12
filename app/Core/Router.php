<?php
class Router {
    private $routes = [];
    private $params = [];

    public function get($route, $handler) {
        $this->addRoute('GET', $route, $handler);
    }

    public function post($route, $handler) {
        $this->addRoute('POST', $route, $handler);
    }

    private function addRoute($method, $route, $handler) {
        $route = preg_replace('/\//', '\/', $route);
        $route = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
        $route = '/^' . $route . '$/';
        $this->routes[$method][] = ['route' => $route, 'handler' => $handler];
    }

    public function dispatch() {
        $url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $url = str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $url);
        $url = '/' . ltrim($url, '/');
        $method = $_SERVER['REQUEST_METHOD'];

        if (!isset($this->routes[$method])) {
            $this->notFound();
            return;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route['route'], $url, $matches)) {
                $this->params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callHandler($route['handler']);
                return;
            }
        }

        $this->notFound();
    }

    private function callHandler($handler) {
        list($controller, $method) = explode('@', $handler);
        if (!class_exists($controller)) {
            $this->notFound();
            return;
        }

        $instance = new $controller();
        if (!method_exists($instance, $method)) {
            $this->notFound();
            return;
        }

        call_user_func_array([$instance, $method], [$this->params]);
    }

    private function notFound() {
        http_response_code(404);
        echo View::render('layout', ['content' => '<h1>404 Not Found</h1><p>Page not found.</p>', 'title' => '404']);
    }
}
