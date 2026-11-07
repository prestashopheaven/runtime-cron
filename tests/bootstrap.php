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

/*
 * Unit tests of runtime-cron run without PrestaShop: only the _PS_VERSION_ guard. CronFrontController needs a real
 * shop and is tested by the fixture module of this monorepo.
 */

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '0.0.0-unit');
}
