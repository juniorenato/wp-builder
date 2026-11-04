<?php

/**
 * Normalizes block field values for storage and render.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Blocks
 */

namespace WPB\Blocks;

/**
 * Converts submitted block fields into the array stored in post meta or block JSON.
 *
 * @since  0.8.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class FieldValue
{
    /**
     * Normalizes a map of fields, dropping unknown keys.
     *
     * @since 0.8.0
     *
     * @param list<array<string, mixed>> $fields Field schemas in declaration order.
     * @param mixed                      $value  Submitted map.
     *
     * @return array<string, mixed> Normalized map.
     */
    public static function normalizeBag(array $fields, mixed $value): array
    {
        $value = self::asArray($value);
        $normalized = [];

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $raw = array_key_exists($name, $value)
                ? $value[$name]
                : ($field['default'] ?? null);

            $normalized[$name] = self::normalize($field, $raw);
        }

        return $normalized;
    }

    /**
     * Normalizes one field value.
     *
     * @since 0.8.0
     *
     * @param array<string, mixed> $field Field schema.
     * @param mixed                $value Submitted value.
     *
     * @return mixed Normalized value.
     *
     * @see https://developer.wordpress.org/reference/functions/sanitize_text_field/
     * @see https://developer.wordpress.org/reference/functions/rest_sanitize_boolean/
     */
    public static function normalize(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'string');

        switch ($type) {
            case 'repeater':
                return self::normalizeRepeater($field, $value);

            case 'integer':
                return self::normalizeInteger($value, (int) ($field['default'] ?? 0));

            case 'number':
                return self::normalizeNumber($value, $field['default'] ?? 0);

            case 'boolean':
                return (bool) rest_sanitize_boolean($value);

            case 'enum':
                return self::normalizeEnum($field, $value);

            case 'image':
                return self::normalizeImage($value);

            case 'string':
            default:
                if (!is_scalar($value)) {
                    $value = $field['default'] ?? '';
                }

                return sanitize_text_field((string) $value);
        }
    }

    /**
     * Builds the REST schema for a meta object property list.
     *
     * @since 0.8.0
     *
     * @param list<array<string, mixed>> $fields Field schemas.
     *
     * @return array<string, array<string, mixed>> REST properties.
     */
    public static function restProperties(array $fields): array
    {
        $properties = [];

        foreach ($fields as $field) {
            $properties[(string) $field['name']] = self::restProperty($field);
        }

        return $properties;
    }

    /**
     * Builds the default map for a field list.
     *
     * @since 0.8.0
     *
     * @param list<array<string, mixed>> $fields Field schemas.
     *
     * @return array<string, mixed> Default values.
     */
    public static function defaults(array $fields): array
    {
        $defaults = [];

        foreach ($fields as $field) {
            $name = (string) $field['name'];
            $defaults[$name] = ($field['type'] ?? '') === 'repeater'
                ? []
                : ($field['default'] ?? null);
        }

        return $defaults;
    }

    /**
     * Normalizes post meta that holds one field bag or one bag per insertion.
     *
     * A legacy value is the field bag itself. Current values are objects keyed
     * by the insertion id stored in the block comment.
     *
     * @since 0.9.0
     *
     * @param list<array<string, mixed>> $fields Field schemas.
     * @param mixed                      $value  Submitted meta value.
     *
     * @return array<string, mixed> Normalized bag, or a map of insertion ids to bags.
     */
    public static function normalizeStoredMeta(array $fields, mixed $value): array
    {
        $value = self::asArray(wp_unslash($value));

        if ($value === [] || array_is_list($value)) {
            return [];
        }

        if (!self::isInstanceMap($value)) {
            return self::normalizeBag($fields, $value);
        }

        $normalized = [];

        foreach ($value as $id => $bag) {
            $normalized[(string) $id] = self::normalizeBag($fields, $bag);
        }

        return $normalized;
    }

    /**
     * Returns the field bag for one insertion.
     *
     * Legacy meta is one bag shared by every insertion. A map returns only the
     * entry for `$instanceId`.
     *
     * @since 0.9.0
     *
     * @param mixed  $meta       Stored post meta.
     * @param string $instanceId Insertion id from the block comment.
     *
     * @return array<string, mixed> Field bag, or an empty array when the insertion has no entry.
     */
    public static function bagForInstance(mixed $meta, string $instanceId): array
    {
        $meta = self::asArray($meta);

        if ($meta === [] || array_is_list($meta)) {
            return [];
        }

        if (!self::isInstanceMap($meta)) {
            return $meta;
        }

        if ($instanceId === '' || !array_key_exists($instanceId, $meta)) {
            return [];
        }

        return self::asArray($meta[$instanceId]);
    }

    /**
     * Checks whether a string is an insertion id.
     *
     * @since 0.9.0
     *
     * @param string $id Candidate id.
     */
    public static function isInstanceId(string $id): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        ) === 1;
    }

    /**
     * Checks whether meta is a map of insertion ids to field bags.
     *
     * @since 0.9.0
     *
     * @param mixed $value Stored meta.
     */
    public static function isInstanceMap(mixed $value): bool
    {
        $value = self::asArray($value);

        if ($value === [] || array_is_list($value)) {
            return false;
        }

        foreach ($value as $id => $bag) {
            if (!is_string($id) || !self::isInstanceId($id)) {
                return false;
            }

            if (is_array($bag) && array_is_list($bag) && $bag !== []) {
                return false;
            }

            if (!is_array($bag) && !$bag instanceof \stdClass) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalizes a repeater into a list of known fields.
     *
     * @since 0.8.0
     *
     * @param array<string, mixed> $field Repeater schema.
     * @param mixed                $value Submitted list.
     *
     * @return list<array<string, mixed>> Normalized items.
     */
    private static function normalizeRepeater(array $field, mixed $value): array
    {
        if (!is_array($value) && !$value instanceof \stdClass) {
            return [];
        }

        $value = self::asArray($value);

        if (!array_is_list($value)) {
            return [];
        }

        $fields = $field['fields'] ?? [];
        $items = [];

        foreach ($value as $item) {
            if (!is_array($item) && !$item instanceof \stdClass) {
                continue;
            }

            $items[] = self::normalizeBag($fields, $item);
        }

        return $items;
    }

    /**
     * Keeps an integer, or the default when the value is not numeric.
     *
     * @since 0.8.0
     *
     * @param mixed $value   Submitted value.
     * @param int   $default Fallback integer.
     */
    private static function normalizeInteger(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * Keeps a number, or the default when the value is not numeric.
     *
     * @since 0.8.0
     *
     * @param mixed $value   Submitted value.
     * @param mixed $default Fallback number.
     */
    private static function normalizeNumber(mixed $value, mixed $default): int|float
    {
        if (!is_numeric($value)) {
            return is_numeric($default) ? (str_contains((string) $default, '.') ? (float) $default : (int) $default) : 0;
        }

        $string = (string) $value;

        return str_contains($string, '.') ? (float) $value : (int) $value;
    }

    /**
     * Keeps an enum member, or the field default.
     *
     * @since 0.8.0
     *
     * @param array<string, mixed> $field Enum schema.
     * @param mixed                $value Submitted value.
     */
    private static function normalizeEnum(array $field, mixed $value): string
    {
        $enum = array_values($field['enum'] ?? []);
        $default = (string) ($field['default'] ?? ($enum[0] ?? ''));
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, $enum, true) ? $value : $default;
    }

    /**
     * Keeps a non-negative attachment ID.
     *
     * @since 0.8.0
     *
     * @param mixed $value Submitted attachment ID.
     */
    private static function normalizeImage(mixed $value): int
    {
        if (!is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * Casts an object or array into an array.
     *
     * @since 0.8.0
     *
     * @param mixed $value Object or array value.
     *
     * @return array<mixed, mixed> Array value, or an empty array.
     */
    private static function asArray(mixed $value): array
    {
        if ($value instanceof \stdClass) {
            return (array) $value;
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Builds one REST schema property.
     *
     * @since 0.8.0
     *
     * @param array<string, mixed> $field Field schema.
     *
     * @return array<string, mixed> REST property schema.
     */
    private static function restProperty(array $field): array
    {
        $type = (string) ($field['type'] ?? 'string');

        switch ($type) {
            case 'boolean':
                return ['type' => 'boolean'];

            case 'integer':
            case 'image':
                return ['type' => 'integer'];

            case 'number':
                return ['type' => 'number'];

            case 'repeater':
                return [
                    'type'  => 'array',
                    'items' => [
                        'type'                 => 'object',
                        'properties'           => self::restProperties($field['fields'] ?? []),
                        'additionalProperties' => false,
                    ],
                ];

            case 'enum':
                return [
                    'type' => 'string',
                    'enum' => array_values($field['enum'] ?? []),
                ];

            case 'string':
            default:
                return ['type' => 'string'];
        }
    }
}
