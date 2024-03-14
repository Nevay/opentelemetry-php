<?php

declare(strict_types=1);

namespace OpenTelemetry\Config\SDK\ComponentProvider\Propagator;

use Nevay\OTelSDK\Configuration\ComponentPlugin;
use Nevay\OTelSDK\Configuration\ComponentProvider;
use Nevay\OTelSDK\Configuration\ComponentProviderRegistry;
use Nevay\OTelSDK\Configuration\Context;
use OpenTelemetry\Context\Propagation\MultiTextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class TextMapPropagatorComposite implements ComponentProvider
{

    /**
     * @param list<ComponentPlugin<TextMapPropagatorInterface>> $properties
     */
    public function createPlugin(array $properties, Context $context): TextMapPropagatorInterface
    {
        $propagators = [];
        foreach ($properties as $plugin) {
            $propagators[] = $plugin->create($context);
        }

        return new MultiTextMapPropagator($propagators);
    }

    public function getConfig(ComponentProviderRegistry $registry): ArrayNodeDefinition
    {
        return $registry->componentNames('composite', TextMapPropagatorInterface::class);
    }
}
