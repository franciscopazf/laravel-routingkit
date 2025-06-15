<?php

namespace Fp\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer;

use Fp\RoutingKit\Contracts\FpEntityInterface;
use Fp\RoutingKit\Contracts\FpFileTransformerInterface;
use Fp\RoutingKit\Features\DataFileTransformersFeature\ObjectTransformer\FpBaseObjectTransformerTrait;
use Illuminate\Support\Collection;

class FpObjectPlainTransformer implements FpFileTransformerInterface
{

    use FpBaseObjectTransformerTrait {
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

    

    private function transformentity(FpEntityInterface $entity): string
    {
        $block = $this->getBlock($entity);
        $block = $this->sanitizeForArray($block, $entity);
        $content = $this->indentBlock($block, 1) . ",\n";

        foreach ($entity->getItems() as $child)
            $content .= $this->transformentity($child);

        return $content;
    }
}
