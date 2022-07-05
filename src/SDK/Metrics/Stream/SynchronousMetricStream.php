<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Metrics\Stream;

use function assert;
use const E_USER_WARNING;
use function extension_loaded;
use GMP;
use function gmp_init;
use function is_int;
use OpenTelemetry\SDK\Metrics\Aggregation;
use OpenTelemetry\SDK\Metrics\AttributeProcessor;
use OpenTelemetry\SDK\Metrics\Data\Data;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Metrics\Exemplar\ExemplarReservoir;
use const PHP_INT_SIZE;
use function sprintf;
use function trigger_error;

final class SynchronousMetricStream implements MetricStream
{
    private MetricAggregator $metricAggregator;
    private Aggregation $aggregation;

    private int $timestamp;

    private DeltaStorage $delta;
    /**
     * @psalm-suppress UndefinedDocblockClass
     * @var int|GMP
     */
    private $readers = 0;
    /**
     * @psalm-suppress UndefinedDocblockClass
     * @var int|GMP
     */
    private $cumulative = 0;

    public function __construct(
        ?AttributeProcessor $attributeProcessor,
        Aggregation $aggregation,
        ?ExemplarReservoir $exemplarReservoir,
        int $startTimestamp
    ) {
        $this->metricAggregator = new MetricAggregator(
            $attributeProcessor,
            $aggregation,
            $exemplarReservoir,
        );
        $this->aggregation = $aggregation;
        $this->timestamp = $startTimestamp;
        $this->delta = new DeltaStorage($aggregation);
    }

    public function writable(): WritableMetricStream
    {
        return $this->metricAggregator;
    }

    public function temporality()
    {
        return Temporality::DELTA;
    }

    public function collectionTimestamp(): int
    {
        return $this->timestamp;
    }

    public function register($temporality): int
    {
        $reader = 0;
        for ($r = $this->readers; ($r & 1) != 0; $r >>= 1, $reader++) {
        }

        if ($reader === (PHP_INT_SIZE << 3) - 1 && is_int($this->readers)) {
            if (!extension_loaded('gmp')) {
                trigger_error(sprintf('GMP extension required to register over %d readers', (PHP_INT_SIZE << 3) - 1), E_USER_WARNING);
                $reader = PHP_INT_SIZE << 3;
            } else {
                assert(is_int($this->cumulative));
                $this->readers = gmp_init($this->readers);
                $this->cumulative = gmp_init($this->cumulative);
            }
        }

        $readerMask = ($this->readers & 1 | 1) << $reader;
        $this->readers ^= $readerMask;
        if ($temporality === Temporality::CUMULATIVE) {
            $this->cumulative ^= $readerMask;
        }

        return $reader;
    }

    public function unregister(int $reader): void
    {
        $readerMask = ($this->readers & 1 | 1) << $reader;
        if (($this->readers & $readerMask) == 0) {
            return;
        }

        $this->delta->collect($reader);

        $this->readers ^= $readerMask;
        if (($this->cumulative & $readerMask) != 0) {
            $this->cumulative ^= $readerMask;
        }
    }

    public function collect(int $reader, ?int $timestamp): Data
    {
        if ($timestamp !== null) {
            $this->delta->add(
                $this->metricAggregator->collect($this->timestamp),
                $this->readers,
            );
            $this->timestamp = $timestamp;
        }

        $cumulative = ($this->cumulative >> $reader & 1) != 0;
        $metric = $this->delta->collect($reader, $cumulative) ?? new Metric([], [], $this->timestamp, -1);

        $temporality = $cumulative
            ? Temporality::CUMULATIVE
            : Temporality::DELTA;

        $data = $this->aggregation->toData(
            $metric->attributes,
            $metric->summaries,
            $this->metricAggregator->exemplars($metric),
            $metric->timestamp,
            $this->timestamp,
            $temporality,
        );

        return $data;
    }
}
