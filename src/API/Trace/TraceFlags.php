<?php

declare(strict_types=1);

namespace OpenTelemetry\API\Trace;

interface TraceFlags
{
    public const SAMPLED = 0x01;
    public const RANDOM_TRACE_ID = 0x02;
    public const DEFAULT = 0x00;
}
