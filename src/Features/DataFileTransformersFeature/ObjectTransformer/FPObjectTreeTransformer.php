<?php

namespace FP\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer;

use FP\RoutingKit\Contracts\FPEntityInterface;
use FP\RoutingKit\Contracts\FPileTransformerInterface;
use FP\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FPBaseObjectTransformerTrait;
use Illuminate\Support\Collection;

class FPObjectTreeTransformer implements FPileTransformerInterface
{
    use FPBaseObjectTransformerTrait {
        __construct as private internalConstruct;
    }

    public function __construct(string $fileString, bool $onlyStringSupport = false)
    {
        $this->internalConstruct($fileString, $onlyStringSupport);
    }

    public function rewrite(Collection $entitys): string
    {
        $content = $this->getFinalContent($entitys);
        return $this->sanatizeContent($content);
        
    }

   public function getFinalContent(Collection $items): string
    {
        $content =  $this->getHeaderBlock() .
            $this->getContentBlock($items) .
            $this->getFooterBlock();

        return $this->sanatizeContent($content);
    }

    public function getContentBlock(Collection $items): string
    {
        $content = '';
        $lastIndex = $items->count() - 1;

        foreach ($items as $i => $entity) {
            $content .= $this->transformEntity($entity);
            if ($i !== $lastIndex)
                $content .= ",\n";
        }

        return $content;
    }

    private function transformEntity(FPEntityInterface $entity): string
    {

        $levelIdent = $this->getLevelIdent($entity);
        // 1. Obtener bloque sin indentar del padre
        $block = $this->getBlock($entity);
        // identa el bloque actual
        $block = $this->indentBlock($block, $levelIdent);

        // 2. Preparar hijos sin indentarlos aún
        $itemBlocks = [];

        foreach ($entity->getItems() as $item)
            // El hijo también sigue este flujo, y no se indenta aquí
            $itemBlocks[] = $this->transformEntity($item);


        // 3. Unir hijos sin indentación (por ahora)
        $joinedItem = implode(",\n", $itemBlocks);
        $indent = $this->getSpacesByLevel($levelIdent + 1);
        // 4. Insertar hijos con indentación calculada al momento
        $block = preg_replace_callback(
            $this->getItemPattern($entity->id),
            function () use ($joinedItem, $entity, $indent) {

                $indent = $this->getSpacesByLevel($this->getLevelIdent($entity));
                // Si no hay hijos, se establece un array vacío
                if (trim($joinedItem) === '') {
                    return  "->setItems([])\n"
                        . $indent . "    ->setEndBlock('{$entity->id}')";
                }

                // Cálculo de indentación en función del nivel actual
                $indentedItem = $joinedItem;

                return "->setItems([\n" .
                    $indentedItem .
                    "\n$indent    ])\n"
                    .  $indent . "    ->setEndBlock('{$entity->id}')";
            },
            $block
        );

        return $block;
    }
}
