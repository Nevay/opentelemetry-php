<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Metrics;

use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\SDK\Metrics\MetricRegistry\MetricWriterInterface;

/**
 * @internal
 */
final class Gauge implements GaugeInterface
{
    private MetricWriterInterface $writer;
    private Instrument $instrument;
    private ReferenceCounterInterface $referenceCounter;

    public function __construct(MetricWriterInterface $writer, Instrument $instrument, ReferenceCounterInterface $referenceCounter)
    {
        $this->writer = $writer;
        $this->instrument = $instrument;
        $this->referenceCounter = $referenceCounter;

        $this->referenceCounter->acquire();
    }

    public function __destruct()
    {
        $this->referenceCounter->release();
    }

    public function record($amount, iterable $attributes = [], $context = null): void
    {
        $this->writer->record($this->instrument, $amount, $attributes, $context);
    }
}
