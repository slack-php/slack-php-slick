<?php

//require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../Slick.php';

SlackPhp\Slick::app()
    ->route('command', '/cool', function (array $payload) {
        return "Thanks for running the `{$payload['command']}` command. You are cool! :thumbsup:";
    })
    ->run();
