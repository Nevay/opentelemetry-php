<?php

declare(strict_types=1);

namespace OpenTelemetry\API\Metrics\Noop;

use OpenTelemetry\API\Metrics\GaugeInterface;

/**
 * @internal
 */
final class NoopGauge implements GaugeInterface
{
    public function record($amount, iterable $attributes = [], $context = null): void
    {
        // no-op
    }
}
