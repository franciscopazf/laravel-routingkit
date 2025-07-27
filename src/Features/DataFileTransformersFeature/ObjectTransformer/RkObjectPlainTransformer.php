<?php

namespace Rk\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer;

use Rk\RoutingKit\Contracts\RkEntityInterface;
use Rk\RoutingKit\Contracts\RkileTransformerInterface;
use Rk\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\RkBaseObjectTransformerTrait;
use Illuminate\Support\Collection;

class RkObjectPlainTransformer implements RkileTransformerInterface
{

    use RkBaseObjectTransformerTrait {
        __construct as private internalConstruct;
    }

    public function __construct(string $fileString, bool $onlyStringSupport = false)
    {
        $this->internalConstruct($fileString, $onlyStringSupport);
    }


    

    public function getFinalContent(Collection $entitys): string
    {
        $content =  $this->getHeaderBlock() .
            $this->getContentBlock($entitys) .
            $this->getFooterBlock();

        return $this->sanatizeContent($content);
    }

    public function getContentBlock(Collection $entitys): string
    {
        $content = '';

        foreach ($entitys as $entity)
            $content .= $this->transformentity($entity);

        return $content;
    }

    

    private function transformentity(RkEntityInterface $entity): string
    {
        $block = $this->getBlock($entity);
        $block = $this->sanitizeForArray($block, $entity);
        $content = $this->indentBlock($block, 1) . ",\n";

        foreach ($entity->getItems() as $child)
            $content .= $this->transformentity($child);

        return $content;
    }
}
