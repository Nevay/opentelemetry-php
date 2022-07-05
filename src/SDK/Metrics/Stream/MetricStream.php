<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Metrics\Stream;

use OpenTelemetry\SDK\Metrics\Data\Data;
use OpenTelemetry\SDK\Metrics\Data\Temporality;

interface MetricStream
{

    /**
     * Returns the internal temporality of this stream.
     *
     * @return string|Temporality internal temporality
     */
    public function temporality();

    /**
     * Returns the last collection timestamp.
     *
     * @return int collection timestamp
     */
    public function collectionTimestamp(): int;

    /**
     * Registers a new reader with the given temporality.
     *
     * @param string|Temporality $temporality temporality to use
     * @return int reader id
     */
    public function register($temporality): int;

    /**
     * Unregisters the given reader.
     *
     * @param int $reader reader id
     */
    public function unregister(int $reader): void;

    /**
     * Collects metric data for the given reader.
     *
     * @param int $reader reader id
     * @param int|null $timestamp timestamp for newly collected data, null to
     *        skip collection of new metric data
     * @return Data metric data
     */
    public function collect(int $reader, ?int $timestamp): Data;
}
