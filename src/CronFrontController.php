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

use PrestaShopHeaven\Runtime\Cqrs\ModuleCommandBus;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * The cron URL of a module (SEC-06, MS-11; was copied in each module's controllers/front/cron.php): a JSON endpoint
 * that runs in the shop of its domain, checks the module's token, refuses a shop where the module is switched off
 * (MS-09), runs the job and answers with its numbers. Errors are logged; the answer never shows their details
 * (SEC-05). It also runs while the shop is in maintenance.
 *
 * In the module: `class <Module>CronModuleFrontController extends CronFrontController` with expectedToken() (a query
 * on the bus) and runCron() (a command on the bus).
 */
abstract class CronFrontController extends \ModuleFrontController
{
    /** The query parameter that carries the token. */
    protected string $tokenParameter = 'cron_token';

    /** Refuse (503) a shop where the module is switched off (MS-09). A module that works for the whole installation turns it off. */
    protected bool $requireEnabledInShop = true;

    /**
     * The token the URL must carry; '' refuses every request.
     */
    abstract protected function expectedToken(ModuleCommandBus $bus): string;

    /**
     * Runs the job for the shop and returns what the answer shows besides success (numbers, flags).
     *
     * @return array<string, mixed>
     */
    abstract protected function runCron(ModuleCommandBus $bus, int $shopId): array;

    /**
     * Answers as JSON (displayAjax) instead of rendering a page.
     */
    public function init(): void
    {
        parent::init();
        $this->ajax = true;
    }

    /**
     * Nothing to render: the answer is JSON.
     */
    public function initContent(): void
    {
    }

    /**
     * A cron job runs during maintenance too.
     */
    protected function displayMaintenancePage(): void
    {
    }

    /**
     * Checks the token and the shop, runs the job and answers.
     */
    public function displayAjax(): void
    {
        try {
            $bus = $this->commandBus();
            if ($bus === null) {
                // A stale container right after an install (PS 8.1): getCommandBus() logged it.
                $this->answer(500, ['success' => false, 'message' => 'The module is not ready; clear the cache.']);

                return;
            }
            if (!CronToken::matches($this->expectedToken($bus), \Tools::getValue($this->tokenParameter))) {
                $this->answer(403, ['success' => false, 'message' => 'Invalid token.']);

                return;
            }
            if ($this->requireEnabledInShop && !\Module::isEnabled((string) $this->module->name)) {
                $this->answer(503, ['success' => false, 'message' => 'The module is switched off in this shop.']);

                return;
            }
            $this->answer(200, ['success' => true] + $this->runCron($bus, (int) $this->context->shop->id));
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog(sprintf('[%s] The cron run failed: %s', $this->module->name, $e->getMessage()), 3);
            $this->answer(500, ['success' => false, 'message' => 'The run failed; see the PrestaShop log.']);
        }
    }

    /**
     * The bus of the module (runtime-cqrs ModuleServices), or null.
     */
    protected function commandBus(): ?ModuleCommandBus
    {
        $bus = method_exists($this->module, 'getCommandBus') ? $this->module->getCommandBus() : null;

        return $bus instanceof ModuleCommandBus ? $bus : null;
    }

    /**
     * Sends the JSON answer with its status code, never cached.
     *
     * @param array<string, mixed> $body
     */
    protected function answer(int $status, array $body): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $this->ajaxRender((string) json_encode($body), null, 'displayAjax');
    }
}
