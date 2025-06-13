<?php

use Symfony\Component\Routing\RouterInterface;

class RouteController extends AbstractController
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    #[Route('/debug/routes', name: 'debug_routes', methods: ['GET'])]
    public function debugRoutes(): JsonResponse
    {
        $routes = [];
        foreach ($this->router->getRouteCollection() as $name => $route) {
            $routes[$name] = $route->getPath();
        }
        return new JsonResponse($routes);
    }
}
