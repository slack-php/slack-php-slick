# Slick Slack

A single-file, dependency-free PHP micro-framework for building simple Slack apps.

**For something more robust, you should use our fully-featured [Slack PHP Framework][1].**

If you are new to Slack app development, you will want to learn about it on [Slack's website][2].

## Installation

### Requirements

Requires PHP 7.3+ with JSON support.

### Via Composer

We recommend using Composer to install Slick, so that autoloading and keeping it up-to-date are easy.

```
composer require slack-php/slick
```

### Manual

However, since Slick has no dependencies, you can directly download and require `Slick.php` or copy & paste the code
directly into your project.

## Basic Usage

This small app responds to the `/cool` slash command by posting a message back to the conversation where
the slack command was used.

> Assumptions:
>
> - You have required the Composer autoloader to autoload Slick, OR you have required `Slick.php` directly.
> - You have `SLACK_SIGNING_KEY` set in your environment such that it is present in `$_SERVER`.

```php
<?php

SlackPhp\Slick::app()
    ->route('command', '/cool', function (array $payload) {
        return "Thanks for running the {$payload['command']} command. You are cool! :thumbsup:";
    })
    ->run();
```

### Breaking It Down

Let's add some comments to that last example, so you know what is going on.

```php
<?php

// Create the Slick app object.
SlackPhp\Slick::app()
    // Add a routable listener to the app to handle logic for a specific interaction.
    ->route(
        // The payload type (e.g., command, block_actions, view_submission, shortcut, and others).
        'command',
        // The payload ID. It's different based on the type. For commands, it is the command name.
        '/cool',
        // The listener that handles the specific interaction's logic.
        function (array $payload) {
            // Any app logic can be done here, including calling Slack's APIs.
            // Whatever you do, it should take less than 3 seconds, so you can "ack" before Slack's timeout.
        
            // Whatever is returned will be included as part of the "ack" (response) body. You can return a string, an
            // associative array, or JSON-serializable object. You should make sure that anything you include in the ack
            // is supported by the type of interaction you are responding to. Many interactions don't require an ack
            // body, so you can also return null, and empty string, or just omit a return statement, entirely.
            return "Thanks for running the {$payload['command']} command. You are cool! :thumbsup:";
        }
    )
    // Add as many routes as you need to handle all your interactions.
    ->route(...)
    ->route(...)
    ->route(...)
    // Runs the Slack app. This includes the following steps:
    // 1. Reads data from the current request (e.g., via $_SERVER and php://input).
    // 2. Validates the request from Slack, including verifying the request signature from the header.
    // 3. Parses the request body into an array of Slack payload data.
    // 4. Determines the payload type and ID.
    // 5. Executes one of the registered listeners based on the payload type and ID.
    // 6. Acks (responds) back to Slack.
    ->run();
```

## Customization

There isn't much to customize, but you can control the behavior of your Slick Slack app when:

1. The incoming request doesn't match one of your routes (`on404()`).
2. An error occurs while processing the incoming request (`orErr()`).

```php
<?php

SlackPhp\Slick::app()
    ->route(...)
    ->route(...)
    ->route(...)
    ->on404(function (array $payload) {
        // Do something custom. This is essentially used as a catch-all listener.
        // If you don't use on404(), then your app throws an error.
    })
    ->onErr(function (Throwable $err) {
        // Do something custom.
        // If you don't use onErr(), then your app calls `error_log()` and acks with a 500-level response.
    })
    ->run();
```

## Helper Functions

If you want to do your own request handling, Slick provides some static helper methods that you can use independent from
the routing features.

- `Slick::validateSignature(string $key, string $signature, int $time, string $body): void` – Validates a Slack request
  signature using their v0 algorithm.
- `Slick::parseRequestBody(string $body): array` – Parses a request body into an associative array of Slack payload
  data. Works for any payload type. Must provide the raw request body as a string (e.g., from `php://input`).
- `Slick::getPayloadType(array $payload): string` – Determines the payload type from an array of payload data.
- `Slick::getPayloadId(array $payload): string` – Determines the payloads routable ID from an array of payload data,
  based on its type.
- `Slack::ack(string $ack): void` – Echos an "ack" response with suitable headers and status code.

[1]: https://github.com/slack-php/slack-php-app-framework
[2]: https://api.slack.com/start
