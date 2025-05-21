<?php
namespace Fp\FullRoute\Clases;
use Illuminate\Support\Str;

class Route
{
    public string $url;
    public string $urlMethod;
    public string $urlController;
    public string $urlAction;
    public string $urlName;
    public array $urlMiddleware = [];
    public array $childrens = [];

    public function __construct(
        string $url,
        string $urlMethod,
        string $urlController,
        string $urlAction,
        string $urlName,
        array $urlMiddleware = [],
        array $childrens = []
    ) {
        // Limpiar doble slash y espacios
        $this->url = trim($url, '/');
        $this->urlMethod = strtolower($urlMethod);
        $this->urlController = trim($urlController, '/');
        $this->urlAction = trim($urlAction, '/');
        $this->urlName = trim($urlName, '/');
        $this->urlMiddleware = array_map('trim', $urlMiddleware);
        $this->childrens = array_filter($childrens, function ($item) {
            return is_a($item, FullRoute::class);
        });
    }
    public function addChild(FullRoute $child): void
    {
        $this->childrens[] = $child;
    }
    public function setUrl(string $url): void
    {
        $this->url = trim($url, '/');
    }
    public function setUrlMethod(string $urlMethod): void
    {
        $this->urlMethod = strtolower($urlMethod);
    }       

}