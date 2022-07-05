<?php

declare(strict_types=1);

namespace OpenTelemetry\API\Metrics\Noop;

use OpenTelemetry\API\Metrics\Counter;

/**
 * @internal
 */
final class NoopCounter implements Counter
{
    public function add($amount, iterable $attributes = [], $context = null): void
    {
        // no-op
    }
}
