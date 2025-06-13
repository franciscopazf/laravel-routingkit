<?php

namespace Fp\RoutingKit\Services\Transformer\Strategies;

use Fp\RoutingKit\Contracts\FpEntityInterface as RoutingKit;
use Fp\RoutingKit\Services\Transformer\TransformerStrategyInterface;
use Fp\RoutingKit\Services\Transformer\Transformers\BlockBuilder;
use Fp\RoutingKit\Services\Route\Strategies\RouteContentManager;
use Illuminate\Support\Collection;

class TreeTransformerStrategy implements TransformerStrategyInterface
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

    public function transform(Collection $routes): string
    {
        $finalNewContent = $this->getFinalContent($routes);
        return $finalNewContent;
    }

    public function getContentBlock(Collection $routes): string
    {
        $content = '';
        $lastIndex = $routes->count() - 1;

        foreach ($routes as $i => $route) {
            $content .= $this->transformRoute($route);
            if ($i !== $lastIndex)
                $content .= ",\n";
        }

        return $content;
    }

    private function transformRoute(RoutingKit $route): string
    {

        $levelIdent = $this->blockBuilder->getLevelIdent($route);
        // 1. Obtener bloque sin indentar del padre
        $block = $this->blockBuilder->getBlock($route);
        // identa el bloque actual
        $block = $this->blockBuilder->indentBlock($block, $levelIdent);

        // 2. Preparar hijos sin indentarlos aún
        $childBlocks = [];

        foreach ($route->getChildrens() as $child)
            // El hijo también sigue este flujo, y no se indenta aquí
            $childBlocks[] = $this->transformRoute($child);


        // 3. Unir hijos sin indentación (por ahora)
        $joinedChildren = implode(",\n", $childBlocks);
        $indent = $this->blockBuilder->getSpacesByLevel($levelIdent + 1);
        // 4. Insertar hijos con indentación calculada al momento
        $block = preg_replace_callback(
            $this->blockBuilder->getChildrenPattern($route->id),
            function () use ($joinedChildren, $route, $indent) {

                $indent = $this->blockBuilder->getSpacesByLevel($this->blockBuilder->getLevelIdent($route));
                // Si no hay hijos, se establece un array vacío
                if (trim($joinedChildren) === '') {
                    return  "->setChildrens([])\n"
                        . $indent . "    ->setEndBlock('{$route->id}')";
                }

                // Cálculo de indentación en función del nivel actual
                $indentedChildren = $joinedChildren;

                return "->setChildrens([\n" .
                    $indentedChildren .
                    "\n$indent    ])\n"
                    .  $indent . "    ->setEndBlock('{$route->id}')";
            },
            $block
        );

        #echo $block . "\n";
        // 5. Finalmente, indentar el bloque completo del padre si lo vas a insertar en otro nivel
        // Pero solo si este bloque no es el raíz
        return $block;
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
