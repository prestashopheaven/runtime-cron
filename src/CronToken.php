<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @author    prestahaven.com
 * @copyright Since 2026 George van Engers
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace PrestaShopHeaven\Runtime\Cron;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The secret of a cron URL (SEC-06): compared in constant time, and an empty expected token never matches (an empty
 * setting would otherwise open the URL to everyone).
 */
final class CronToken
{
    /**
     * True when the given token is exactly the expected one and the expected one is not empty.
     */
    public static function matches(string $expected, mixed $given): bool
    {
        return $expected !== '' && is_string($given) && hash_equals($expected, $given);
    }

    /**
     * A new random token (32 hexadecimal characters), as ModuleDefinition::GENERATED_CONFIGURATION makes them.
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
