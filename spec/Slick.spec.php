<?php

declare(strict_types=1);

namespace SlackPhp\Spec;

use SlackPhp\Slick;

describe("Slick", function () {
    describe("::getPayloadType()", function () {
        it("returns 'type' field if present", function () {
            $payload = ['type' => 'foo'];
            expect(Slick::getPayloadType($payload))->toBe('foo');
        });

        it("throws an exception if no 'type' present", function () {
            $resolve = function () {
                Slick::getPayloadType([]);
            };
            expect($resolve)->toThrow();
        });

        it("returns 'command' field if no 'type' present, but 'command' is", function () {
            $payload = ['command' => '/foo'];
            expect(Slick::getPayloadType($payload))->toBe('command');
        });
    });
});
