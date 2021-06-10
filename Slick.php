<?php

declare(strict_types=1);

namespace SlackPhp;

use Exception;
use JsonException;
use RuntimeException;
use Throwable;

use function abs;
use function error_log;
use function file_get_contents;
use function hash_equals;
use function hash_hmac;
use function header;
use function headers_sent;
use function http_response_code;
use function is_string;
use function json_decode;
use function json_encode;
use function parse_str;
use function strlen;
use function substr;
use function time;
use function urldecode;

/**
 * Slick is a quick Slack app micro-framework that provides just the essentials.
 */
final class Slick
{
    /** @var array<string, array<string, callable>> */
    private $routes;
    /** @var array<string, string> */
    private $server;
    /** @var string */
    private $body;
    /** @var callable */
    private $errorHandler;
    /** @var callable */
    private $catchAllListener;

    /**
     * Creates a Slick Slack app pulling request data from $_SERVER.
     *
     * @return self
     */
    public static function app(): self
    {
        return new self($_SERVER, file_get_contents('php://input') ?: '');
    }

    /**
     * @param array<string, string> $server
     * @param string $body
     */
    public function __construct(array $server, string $body)
    {
        $this->routes = [];
        $this->server = $server;
        $this->body = $body;
        $this->catchAllListener = function (array $payload) {
            $type = self::getPayloadType($payload);
            $id = self::getPayloadId($payload);
            self::err('routing', "Could not route payload (Type: {$type}, ID: {$id})", 404);
        };
        $this->errorHandler = function (Throwable $error) {
            error_log($error->getMessage());
            http_response_code($error->getCode() ?: 500);
            echo '';
        };
    }

    /**
     * Registers a listener for a specific payload type and ID.
     *
     * @param string|null $type
     * @param string|null $id
     * @param callable $listener
     * @return $this
     */
    public function route(?string $type, ?string $id, callable $listener): self
    {
        $this->routes[$type][$id] = $listener;

        return $this;
    }

    /**
     * Registers a catch-all listener when to be run when no other registered listener applies.
     *
     * @param callable $listener
     * @return $this
     */
    public function on404(callable $listener): self
    {
        $this->catchAllListener = $listener;

        return $this;
    }

    /**
     * Registers an error handler to handle exceptions thrown when the app is run.
     *
     * @param callable $handler
     * @return $this
     */
    public function onErr(callable $handler): self
    {
        $this->errorHandler = $handler;

        return $this;
    }

    /**
     * Processes the incoming request from Slack and returns the ack content to respond with.
     *
     * @return string
     * @throws Exception if anything goes wrong.
     */
    public function processRequest(): string
    {
        $this->validateRequest();
        $payload = self::parseRequestBody($this->body);
        $type = self::getPayloadType($payload);
        $id = self::getPayloadId($payload);
        $route = $this->routes[$type][$id] ?? $this->catchAllListener;
        $result = $route($payload);
        return $this->prepareAck($result);
    }

    /**
     * Runs the app to respond to Slack requests.
     *
     * Does the following:
     * 1. Reads data from the current request (e.g., via $_SERVER).
     * 2. Validates the request, including verifying the request signature that Slack sends as a header.
     * 3. Parses the request body into an array of Slack payload data.
     * 4. Determines the payload type and ID.
     * 5. Routes to one of the registered listeners based on the payload type and ID.
     * 6. Acks back to Slack.
     */
    public function run(): void
    {
        try {
            self::ack($this->processRequest());
        } catch (Throwable $error) {
            ($this->errorHandler)($error);
        }
    }

    private function validateRequest(): void
    {
        if (!isset($this->server['REQUEST_METHOD']) || $this->server['REQUEST_METHOD'] !== 'POST') {
            self::err('auth', 'Only POST requests are supported', 401);
        }

        if (!isset($this->server['SLACK_SIGNING_KEY'])) {
            self::err('auth', 'Missing SLACK_SIGNING_KEY in environment', 401);
        }

        if (!isset($this->server['HTTP_X_SLACK_SIGNATURE'])) {
            self::err('auth', 'No signature provided in Slack request', 401);
        }

        if (!isset($this->server['HTTP_X_SLACK_REQUEST_TIMESTAMP'])) {
            self::err('auth', 'No timestamp provided in Slack request', 401);
        }

        self::validateSignature(
            $this->server['SLACK_SIGNING_KEY'],
            $this->server['HTTP_X_SLACK_SIGNATURE'],
            (int) $this->server['HTTP_X_SLACK_REQUEST_TIMESTAMP'],
            $this->body
        );
    }

    /**
     * Prepares the result of a listener as an ack response body.
     *
     * @param mixed $data
     * @return string
     */
    private function prepareAck($data = null): string
    {
        if ($data === null) {
            return '';
        }

        if (is_string($data)) {
            $data = ['text' => $data];
        }

        try {
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException $err) {
            self::err('ack', 'Invalid JSON while encoding ack', 500);
        }
    }

    /**
     * Responds (acks) to a Slack request.
     *
     * @param string $ack
     * @throws Exception if headers have already been sent.
     */
    public static function ack(string $ack): void
    {
        if (headers_sent()) {
            self::err('ack', 'HTTP headers already sent', 500);
        }

        http_response_code(200);
        $contentLength = strlen($ack);
        if ($contentLength > 0) {
            header('Content-Type: application/json');
            header('Content-Length: ' . $contentLength);
        }

        echo $ack;
    }

    /**
     * Validates the signature of an incoming request from Slack.
     *
     * @param string $key
     * @param string $signature
     * @param int $time
     * @param string $body
     * @throws Exception if signature validation fails
     */
    public static function validateSignature(string $key, string $signature, int $time, string $body): void
    {
        if (abs(time() - $time) > 300) {
            self::err('auth', 'Timestamp is too old or too new (clock skew / time drift)', 401);
        }

        if (substr($signature, 0, 3) !== 'v0=') {
            self::err('auth', 'Missing or unsupported signature version', 401);
        }

        $expectedSignature = 'v0=' . hash_hmac('sha256', "v0:{$time}:{$body}", $key);

        if (!hash_equals($signature, $expectedSignature)) {
            self::err('auth', 'Signature (v0) failed validation', 401);
        }
    }

    /**
     * Parses an incoming Slack request body into key-pairs.
     *
     * @param string $body
     * @return array<string, mixed>
     * @throws Exception if body cannot be parsed.
     */
    public static function parseRequestBody(string $body): array
    {
        if (empty($body)) {
            self::err('body', 'Body must not be empty', 400);
        }

        try {
            if ($body[0] === '{') {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } else {
                parse_str($body, $data);
                if (isset($data['payload'])) {
                    $data = json_decode(urldecode($data['payload']), true, 512, JSON_THROW_ON_ERROR);
                }
            }
        } catch (JsonException $err) {
            self::err('body', 'Invalid JSON while decoding body', 400, $err);
        }

        if (empty($data)) {
            self::err('body', 'Parsed body yielded no data', 400);
        }

        return $data;
    }

    /**
     * Gets the Slack payload type.
     *
     * @param array<string, mixed> $payload
     * @return string
     * @throws Exception if payload type cannot be detected
     */
    public static function getPayloadType(array $payload): string
    {
        if (isset($payload['type'])) {
            return $payload['type'];
        }

        if (isset($payload['command'])) {
            return 'command';
        }

        self::err('payload', 'No payload type detected', 400);
    }

    /**
     * Gets the type-specific ID of the payload that can be used for routing.
     *
     * @param array<string, mixed> $payload
     * @return string
     * @throws Exception if payload ID cannot be determined
     */
    public static function getPayloadId(array $payload): string
    {
        switch (self::getPayloadType($payload)) {
            case 'block_actions':
                $id = $payload['actions'][0]['action_id'] ?? null;
                break;
            case 'block_suggestion':
                $id = $payload['action_id'] ?? null;
                break;
            case 'command':
                $id = $payload['command'] ?? null;
                break;
            case 'event_callback':
                $id = $payload['event']['type'] ?? null;
                break;
            case 'message_action':
            case 'shortcut':
            case 'workflow_step_edit':
                $id = $payload['callback_id'] ?? null;
                break;
            case 'view_closed':
            case 'view_submission':
                $id = $payload['view']['callback_id'] ?? null;
                break;
            default:
                self::err('payload', 'Unexpected payload type', 400);
        }

        if ($id === null) {
            self::err('payload', 'Payload ID was missing from expected field', 400);
        }

        return $id;
    }

    /**
     * @param string $type
     * @param string $message
     * @param int $statusCode
     * @param Throwable|null $prev
     * @return no-return
     * @throws RuntimeException
     */
    private static function err(string $type, string $message, int $statusCode, ?Throwable $prev = null): void
    {
        throw new RuntimeException("Slack App Error ({$type}): {$message}", $statusCode, $prev);
    }
}
