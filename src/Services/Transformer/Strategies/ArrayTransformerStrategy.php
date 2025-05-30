<?php

namespace Fp\FullRoute\Services\Transformer\Strategies;

use Fp\FullRoute\Contracts\FpEntityInterface as FullRoute;
use Fp\FullRoute\Services\Transformer\TransformerStrategyInterface;
use Fp\FullRoute\Services\Transformer\Transformers\BlockBuilder;
use Fp\FullRoute\Services\Route\Strategies\RouteContentManager;
use Illuminate\Support\Collection;

class ArrayTransformerStrategy implements TransformerStrategyInterface
{
    public function __construct(
        private RouteContentManager $contentManager,
        private ?BlockBuilder $blockBuilder = null
    ) {

        if (!$this->blockBuilder) 
            $this->blockBuilder = BlockBuilder::make($this->contentManager);
        
    }


    public function getFinalContent(Collection $routes): string
    {
        $content =  $this->blockBuilder->getHeaderBlock() .
            $this->getContentBlock($routes) .
            $this->getFooterBlock();

        return $this->blockBuilder->sanatizeContent($content);
    }

    public function getContentBlock(Collection $routes): string
    {
        $content = '';

        foreach ($routes as $route)
            $content .= $this->transformRoute($route);

        return $content;
    }

    public function transform(Collection $routes): string
    {
        $finalNewContent = $this->getFinalContent($routes);

        return $finalNewContent;
    }

    private function transformRoute(FullRoute $route): string
    {
        $block = $this->blockBuilder->getBlock($route);
        $block = $this->blockBuilder->sanitizeForArray($block, $route);
        $content = $this->blockBuilder->indentBlock($block, 1) . ",\n";

        foreach ($route->getChildrens() as $child)
            $content .= $this->transformRoute($child);

        return $content;
    }

    public function getHeaderBlock(): string
    {
        return $this->blockBuilder->getHeaderBlock();
    }

    public function getFooterBlock(): string
    {
        return "\n];\n";
    }
}
