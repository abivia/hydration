<?php

declare(strict_types=1);

namespace Abivia\Hydration;

/**
 * Adding this interface to a class will allow it to be automatically bound to
 * properties in the class.
 */
interface Hydratable
{

    /**
     * Load configuration data into an object structure.
     *
     * @param string|object|array $config Configuration data either as a string or the result of
     *      decoding a configuration file.
     * @param array|null $options Options are:
     * - `parent` object: A reference to the object containing $target (if any).
     * - `source` string [json]|object|yaml: Format of the data in $config, default json.
     * - `strict` bool [true]: Throw errors on any failure, otherwise log for getErrors().
     *
     * The application can pass options that begin with an underscore. These will be passed through
     * unchanged.
     *
     * @return bool True if all fields passed validation; if in strict mode
     *              true when all fields are defined class properties.
     *
     * @throws HydrationException
     */
    public function hydrate($config, ?array $options = []): bool;

}