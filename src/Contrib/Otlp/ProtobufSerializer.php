<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Otlp;

use AssertionError;
use function base64_decode;
use function bin2hex;
use Exception;
use function get_class;
use Google\Protobuf\DescriptorPool;
use Google\Protobuf\Internal\GPBLabel;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\Message;
use InvalidArgumentException;
use function json_decode;
use function json_encode;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use function lcfirst;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use function property_exists;
use function sprintf;
use function ucwords;

/**
 * @internal
 *
 * @psalm-type SUPPORTED_CONTENT_TYPES = self::PROTOBUF|self::JSON|self::NDJSON
 */
final class ProtobufSerializer
{
    private const PROTOBUF = 'application/x-protobuf';
    private const JSON = 'application/json';
    private const NDJSON = 'application/x-ndjson';

    private string $contentType;

    private function __construct(string $contentType)
    {
        $this->contentType = $contentType;
    }

    public static function getDefault(): ProtobufSerializer
    {
        return new self(self::PROTOBUF);
    }

    /**
     * @psalm-param TransportInterface<SUPPORTED_CONTENT_TYPES> $transport
     */
    public static function forTransport(TransportInterface $transport): ProtobufSerializer
    {
        switch ($contentType = $transport->contentType()) {
            case self::PROTOBUF:
            case self::JSON:
            case self::NDJSON:
                return new self($contentType);
            default:
                throw new InvalidArgumentException(sprintf('Not supported content type "%s"', $contentType));
        }
    }

    public function serializeTraceId(string $traceId): string
    {
        switch ($this->contentType) {
            case self::PROTOBUF:
                return $traceId;
            case self::JSON:
            case self::NDJSON:
                return base64_decode(bin2hex($traceId));
            default:
                throw new AssertionError();
        }
    }

    public function serializeSpanId(string $spanId): string
    {
        switch ($this->contentType) {
            case self::PROTOBUF:
                return $spanId;
            case self::JSON:
            case self::NDJSON:
                return base64_decode(bin2hex($spanId));
            default:
                throw new AssertionError();
        }
    }

    public function serialize(Message $message): string
    {
        switch ($this->contentType) {
            case self::PROTOBUF:
                return $message->serializeToString();
            case self::JSON:
                return self::postProcessJsonEnumValues($message, $message->serializeToJsonString());
            case self::NDJSON:
                return self::postProcessJsonEnumValues($message, $message->serializeToJsonString()) . "\n";
            default:
                throw new AssertionError();
        }
    }

    /**
     * @throws Exception
     */
    public function hydrate(Message $message, string $payload): void
    {
        switch ($this->contentType) {
            case self::PROTOBUF:
                $message->mergeFromString($payload);

                break;
            case self::JSON:
            case self::NDJSON:
                // @phan-suppress-next-line PhanParamTooManyInternal
                $message->mergeFromJsonString($payload, true);

                break;
            default:
                throw new AssertionError();
        }
    }

    /**
     * Workaround until protobuf exposes `FormatEnumsAsIntegers` option.
     *
     * [JSON Protobuf Encoding](https://opentelemetry.io/docs/specs/otlp/#json-protobuf-encoding):
     * > Values of enum fields MUST be encoded as integer values.
     *
     * @see https://github.com/open-telemetry/opentelemetry-php/issues/978
     * @see https://github.com/protocolbuffers/protobuf/pull/12707
     */
    private static function postProcessJsonEnumValues(Message $message, string $payload): string
    {
        $data = json_decode($payload);
        unset($payload);
        self::traverseDescriptor($data, get_class($message));

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param class-string $class
     */
    private static function traverseDescriptor(object $data, string $class): void
    {
        foreach (self::fields($class) as $name => $field) {
            if (!property_exists($data, $name)) {
                continue;
            }

            if ($field->repeated) {
                foreach ($data->$name as $key => $value) {
                    if ($field->message) {
                        self::traverseDescriptor($value, $field->message);
                    }
                    if ($field->enums) {
                        $data->$name[$key] = $field->enums[$value] ?? $value;
                    }
                }
            } else {
                if ($field->message) {
                    self::traverseDescriptor($data->$name, $field->message);
                }
                if ($field->enums) {
                    $data->$name = $field->enums[$data->$name] ?? $data->$name;
                }
            }
        }
    }

    /**
     * @param class-string $class
     * @psalm-return array<string, object{
     *     message: ?class-string,
     *     enums: ?array,
     *     repeated: bool,
     * }>
     */
    private static function fields(string $class): array
    {
        static $cache = [];
        if ($fields = $cache[$class] ?? null) {
            return $fields;
        }
        /** @phpstan-ignore-next-line */
        if (!$desc = DescriptorPool::getGeneratedPool()->getDescriptorByClassName($class)) {
            return [];
        }

        $fields = [];
        for ($i = 0, $n = $desc->getFieldCount(); $i < $n; $i++) {
            $field = $desc->getField($i);
            $type = $field->getType();
            if ($type !== GPBType::MESSAGE && $type !== GPBType::ENUM) {
                continue;
            }

            $fieldDescriptor = new class() {
                public ?string $message = null;
                public ?array $enums = null;
                public bool $repeated;
            };

            if ($type === GPBType::MESSAGE) {
                $fieldDescriptor->message = $field->getMessageType()->getClass();
            }
            if ($type === GPBType::ENUM) {
                $enum = $field->getEnumType();
                $fieldDescriptor->enums = [];
                for ($e = 0, $m = $enum->getValueCount(); $e < $m; $e++) {
                    $value = $enum->getValue($e);
                    $fieldDescriptor->enums[$value->getName()] = $value->getNumber();
                }
            }
            $fieldDescriptor->repeated = $field->getLabel() === GPBLabel::REPEATED;

            $name = lcfirst(strtr(ucwords($field->getName(), '_'), ['_' => '']));
            $fields[$name] = $fieldDescriptor;
        }

        /** @psalm-suppress LessSpecificReturnStatement */
        return $cache[$class] = $fields;
    }
}
