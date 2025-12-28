<?php
declare(strict_types=1);

/**
 * 位置：/Public/tools/line_webhook.php
 * 說明：對外 Webhook 入口，單純引入核心並執行。
 * 好處：Public 下的 URL 穩定，核心邏輯仍放在專案根 /tools。
 */

require_once __DIR__ . '/../../tools/line_webhook_core.php';
