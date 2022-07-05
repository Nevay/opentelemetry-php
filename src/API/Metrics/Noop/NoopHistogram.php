<?php

declare(strict_types=1);

namespace OpenTelemetry\API\Metrics\Noop;

use OpenTelemetry\API\Metrics\Histogram;

/**
 * @internal
 */
final class NoopHistogram implements Histogram
{
    public function record($amount, iterable $attributes = [], $context = null): void
    {
        // no-op
    }
}
