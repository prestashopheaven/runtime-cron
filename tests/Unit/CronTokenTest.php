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

namespace PrestaShopHeaven\Runtime\Cron\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrestaShopHeaven\Runtime\Cron\CronToken;

/**
 * The token of a cron URL: only the exact, non-empty expected token opens it.
 */
final class CronTokenTest extends TestCase
{
    public function testTheExactTokenMatches(): void
    {
        self::assertTrue(CronToken::matches('a1b2c3', 'a1b2c3'));
    }

    /**
     * Tokens that must never open the URL.
     *
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function refused(): iterable
    {
        yield 'another token' => ['a1b2c3', 'a1b2c4'];
        yield 'a prefix' => ['a1b2c3', 'a1b2'];
        yield 'empty given' => ['a1b2c3', ''];
        yield 'empty expected, empty given' => ['', ''];
        yield 'empty expected, any given' => ['', 'x'];
        yield 'not a string (Tools::getValue false)' => ['a1b2c3', false];
        yield 'an array' => ['a1b2c3', ['a1b2c3']];
        yield 'null' => ['a1b2c3', null];
    }

    #[DataProvider('refused')]
    public function testEverythingElseIsRefused(string $expected, mixed $given): void
    {
        self::assertFalse(CronToken::matches($expected, $given));
    }

    public function testGeneratedTokensAre32HexadecimalCharactersAndDiffer(): void
    {
        $first = CronToken::generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $first);
        self::assertNotSame($first, CronToken::generate());
    }
}
