<?php

namespace Fp\RoutingKit\Services\Transformer;

use Fp\RoutingKit\Services\Route\Strategies\RouteContentManager;
use Illuminate\Support\Collection;

class TransformerContext
{
    private TransformerStrategyInterface $strategy;

    public function __construct(
        private RouteContentManager $contentManager,
        private ?string $type = 'file_array',
        private ?Collection $routes = null
    ) {

        $this->strategy = $this->factoryStrategy($this->type);
    }

    public static function make(
        RouteContentManager $contentManager,
        ?string $type = 'file_array',
        ?Collection $routes = null
    ): self {
        return new self($contentManager, $type, $routes);
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function setCollectionRoutes(Collection $routes): self
    {
        $this->routes = $routes;
        return $this;
    }

    public function setStrategy(TransformerStrategyInterface $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    public function transformAndWrite(): void
    {
        if (!$this->strategy)
            $this->strategy = $this->factoryStrategy();


        $transformedContent = $this->strategy->transform($this->routes);
        $this->contentManager->putContents($transformedContent);
    }

    public function factoryStrategy(?string $type = null): TransformerStrategyInterface
    {
        if ($type)
            $this->type = $type;

        return match ($this->type) {
            'file_array' => new Strategies\ArrayTransformerStrategy($this->contentManager),
            'file_tree' => new Strategies\TreeTransformerStrategy($this->contentManager), //new Strategies\TreeTransformerStrategy($this->manager),
            default => throw new \InvalidArgumentException("Tipo de transformación no soportado: $type"),
        };
    }
}
