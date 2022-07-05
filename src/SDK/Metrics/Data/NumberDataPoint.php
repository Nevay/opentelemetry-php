<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Metrics\Data;

use OpenTelemetry\SDK\Attributes;

final class NumberDataPoint
{
    /**
     * @var float|int
     * @readonly
     */
    public $value;
    /**
     * @readonly
     */
    public Attributes $attributes;
    /**
     * @readonly
     */
    public int $startTimestamp;
    /**
     * @readonly
     */
    public int $timestamp;
    /**
     * @readonly
     */
    public iterable $exemplars = [];
    /**
     * @param float|int $value
     */
    public function __construct($value, Attributes $attributes, int $startTimestamp, int $timestamp, iterable $exemplars = [])
    {
        $this->value = $value;
        $this->attributes = $attributes;
        $this->startTimestamp = $startTimestamp;
        $this->timestamp = $timestamp;
        $this->exemplars = $exemplars;
    }
}
