<?php

namespace FpF\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer;

use FpF\RoutingKit\Contracts\FpFEntityInterface;
use FpF\RoutingKit\Contracts\FpFileTransformerInterface;
use FpF\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FpFBaseObjectTransformerTrait;
use Illuminate\Support\Collection;

class FpFObjectPlainTransformer implements FpFileTransformerInterface
{

    use FpFBaseObjectTransformerTrait {
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

    

    private function transformentity(FpFEntityInterface $entity): string
    {
        $block = $this->getBlock($entity);
        $block = $this->sanitizeForArray($block, $entity);
        $content = $this->indentBlock($block, 1) . ",\n";

        foreach ($entity->getItems() as $child)
            $content .= $this->transformentity($child);

        return $content;
    }
}
