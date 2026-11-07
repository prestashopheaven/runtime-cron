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

namespace PrestaShopHeaven\Runtime\Cron\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * CronFrontController in a real shop, through the cron URL of the fixture module (HEAVEN_FIXTURE_MODULE): the right
 * token runs the job and answers JSON, a wrong or missing token gets 403, a failing job gets 500 without its details,
 * and a shop where the module is switched off gets 503 (multistore round).
 */
final class CronFrontControllerTest extends TestCase
{
    public function testTheRightTokenRunsTheJobForTheShopOfTheUrl(): void
    {
        $before = (int) \Configuration::getGlobalValue('HEAVENMONOINTEGRATION_RUNS');

        $response = $this->call(['cron_token' => $this->token()]);

        self::assertSame(200, $response['status'], $response['body']);
        $body = json_decode($response['body'], true);
        self::assertSame(['success' => true, 'shop' => (int) \Configuration::get('PS_SHOP_DEFAULT'), 'runs' => $before + 1], $body);
        self::assertStringContainsString('application/json', $response['headers']);
        self::assertMatchesRegularExpression('/Cache-Control: no-store/i', $response['headers']);
    }

    /**
     * Requests that must be refused.
     *
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function refusedRequests(): iterable
    {
        yield 'wrong token' => [['cron_token' => 'wrong']];
        yield 'no token' => [[]];
        yield 'empty token' => [['cron_token' => '']];
        yield 'token in another parameter' => [['token' => 'any']];
    }

    /**
     * @param array<string, string> $query
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedRequests')]
    public function testAWrongTokenGets403AndRunsNothing(array $query): void
    {
        // The cron request ran in another process: read the counter from the database, not this process's cache.
        \Configuration::loadConfiguration();
        $before = (int) \Configuration::getGlobalValue('HEAVENMONOINTEGRATION_RUNS');

        $response = $this->call($query);

        self::assertSame(403, $response['status']);
        self::assertSame(['success' => false, 'message' => 'Invalid token.'], json_decode($response['body'], true));
        \Configuration::loadConfiguration();
        self::assertSame($before, (int) \Configuration::getGlobalValue('HEAVENMONOINTEGRATION_RUNS'));
    }

    public function testAFailingJobGets500WithoutItsDetails(): void
    {
        $response = $this->call(['cron_token' => $this->token(), 'fail' => '1']);

        self::assertSame(500, $response['status']);
        self::assertSame(['success' => false, 'message' => 'The run failed; see the PrestaShop log.'], json_decode($response['body'], true));
        self::assertStringNotContainsString('secret details', $response['body']);
    }

    /**
     * PrestaShop's dispatcher already answers 404 for the front controller of a module that is switched off in the shop
     * (8.1 and 9.x); the controller's own 503 check covers the rest. Either way the job does not run.
     */
    public function testAShopWhereTheModuleIsOffRunsNothing(): void
    {
        if (!\Shop::isFeatureActive() || count(\Shop::getShops(true, null, true)) < 2) {
            self::markTestSkipped('Needs a second shop: the multistore round of ps-module shops run covers it.');
        }
        $shops = array_values(array_map('intval', \Shop::getShops(true, null, true)));
        $other = $shops[1];
        $module = \Module::getInstanceByName(HEAVEN_FIXTURE_MODULE);
        \Shop::setContext(\Shop::CONTEXT_SHOP, $other);
        $module->disable();
        try {
            \Configuration::loadConfiguration();
            $before = (int) \Configuration::getGlobalValue('HEAVENMONOINTEGRATION_RUNS');

            $response = $this->call(['cron_token' => $this->token()], $other);

            self::assertContains($response['status'], [404, 503], $response['body']);
            if ($response['status'] === 503) {
                self::assertSame(['success' => false, 'message' => 'The module is switched off in this shop.'], json_decode($response['body'], true));
            }
            \Configuration::loadConfiguration();
            self::assertSame($before, (int) \Configuration::getGlobalValue('HEAVENMONOINTEGRATION_RUNS'), 'the job did not run');
        } finally {
            $module->enable();
            \Shop::setContext(\Shop::CONTEXT_SHOP, (int) \Configuration::get('PS_SHOP_DEFAULT'));
        }
    }

    /**
     * The generated token of the fixture.
     */
    private function token(): string
    {
        \Configuration::loadConfiguration();

        return (string) \Configuration::getGlobalValue('HEAVENMONOINTEGRATION_CRON_TOKEN');
    }

    /**
     * A GET on the cron URL of a shop, from inside the shop container (the host of the URL resolves to this web server).
     *
     * @param array<string, string> $query
     *
     * @return array{status: int, headers: string, body: string}
     */
    private function call(array $query, ?int $shopId = null): array
    {
        $url = \Context::getContext()->link->getModuleLink(HEAVEN_FIXTURE_MODULE, 'cron', $query, null, null, $shopId);
        $parts = (array) parse_url($url);
        $curl = curl_init($url);
        self::assertNotFalse($curl);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECT_TO => [($parts['host'] ?? 'localhost') . ':' . ($parts['port'] ?? 80) . ':127.0.0.1:80'],
        ]);
        $response = (string) curl_exec($curl);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        return [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'headers' => substr($response, 0, $headerSize),
            'body' => substr($response, $headerSize),
        ];
    }
}
