<?php

declare(strict_types=1);

namespace OpenTelemetry\API\Metrics;

use OpenTelemetry\Context\ContextInterface;

/**
 * @experimental
 */
interface GaugeInterface
{

    /**
     * @param float|int $amount current absolute value to record
     * @param iterable<non-empty-string, string|bool|float|int|array|null> $attributes
     *        attributes of the data point
     * @param ContextInterface|false|null $context execution context
     *
     * @see https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/metrics/api.md#record-1
     */
    public function record($amount, iterable $attributes = [], $context = null): void;
}
