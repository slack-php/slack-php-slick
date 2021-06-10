<?php

declare(strict_types=1);

namespace SlackPhp\Spec;

use SlackPhp\Slick;

describe("e2e", function () {
    it('can handle commands', function () {
        $app = testApp(['command' => '/foo'])
            ->route('command', '/foo', function () {
                return 'bar';
            });
        expect(appRunner($app))->toEcho('{"text":"bar"}');
    });

    it('can handle block actions', function () {
        $state = 0;
        $app = testApp([
            'type' => 'block_actions',
            'actions' => [['action_id' => 'foo']]
        ]);
        $app->route('block_actions', 'foo', function () use (&$state) {
            $state++;
        });
        expect(appRunner($app))->toEcho('');
        expect($state)->toBe(1);
    });

    it('fails when missing signing secret', function () {
        $app = testApp([], ['SLACK_SIGNING_KEY' => null]);
        expect(appRunner($app, 401))->toEcho('');
    });

    it('fails when signature does not match', function () {
        $app = testApp([], ['HTTP_X_SLACK_SIGNATURE' => 'v0=foo']);
        expect(appRunner($app, 401))->toEcho('');
    });
});

function testApp(array $payload = [], array $server = []): Slick
{
    $body = $payload ? json_encode($payload) : '';
    $server += [
        'REQUEST_METHOD' => 'POST',
        'SLACK_SIGNING_KEY' => 'abc123',
        'HTTP_X_SLACK_REQUEST_TIMESTAMP' => time(),
        'HTTP_X_SLACK_SIGNATURE' => 'v0=' . hash_hmac('sha256', implode(':', ['v0', time(), $body]), 'abc123'),
    ];

    return new Slick($server, $body);
}

function appRunner(Slick $app, int $expectedStatus = 200): callable
{
    allow('error_log')->toBeCalled();
    allow('headers_sent')->toBeCalled()->andReturn(false);
    allow('http_response_code')->toBeCalled()->andRun(function (int $status) use ($expectedStatus) {
        expect($status)->toBe($expectedStatus);
    });
    allow('header')->toBeCalled();

    return function () use (&$app) {
        $app->run();
    };
}
