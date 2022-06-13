# Documentation

Getting started with StdioLogHelper, use Composer command to add the package to your Laravel project's dependencies.

```bash
composer require justinianus/stdio-log
composer dump-autoload
```

Then register new package service provider to config/app.php

```php
'providers' => [
	Justinianus\StdioLog\StdioServiceProvider::class,
],
```

## Setup Channel

Create new channel 'slack-log' for Slack message into config/logging.php to send message log via Slack

```php
'slack-log' => [
	'driver' => 'slack',
	'url' => env('LOG_SLACK_WEBHOOK_URL'),
	'username' => 'Laravel Log',
	'emoji' => ':boom:',
	'level' => env('LOG_LEVEL', 'critical'),
],
```

Add environment variable into .env for the incoming webhook receive from Slack application.

```bash
LOG_SLACK_WEBHOOK_URL=YOUR_INCOMING_WEBHOOK_SLACK_URL
```

## Usage

Declare library before using.

```php
use Justinianus\StdioLog\Helpers\StdioLogHelper;
```

### Log for Back-End

```php
StdioLogHelper::writeFileLog(StdioLogHelper::LOG_LEVEL_CONST, 'log-channel-name', Exception $exception, Request $request, [$booleanSendSlack]);
```

### Callback for Front-End

```php
StdioLogHelper::callback(Request $request, [$booleanSendSlack, StdioLogHelper::LOG_LEVEL_CONST, 'log-channel-name']);
```