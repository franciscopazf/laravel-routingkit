<?php

namespace FP\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer;

use FP\RoutingKit\Contracts\FPEntityInterface;
use FP\RoutingKit\Contracts\FPileTransformerInterface;
use FP\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FPBaseObjectTransformerTrait;
use Illuminate\Support\Collection;

class FPObjectPlainTransformer implements FPileTransformerInterface
{

    use FPBaseObjectTransformerTrait {
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

    

    private function transformentity(FPEntityInterface $entity): string
    {
        $block = $this->getBlock($entity);
        $block = $this->sanitizeForArray($block, $entity);
        $content = $this->indentBlock($block, 1) . ",\n";

        foreach ($entity->getItems() as $child)
            $content .= $this->transformentity($child);

        return $content;
    }
}
