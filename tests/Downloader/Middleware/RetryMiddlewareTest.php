<?php

declare(strict_types=1);

/**
 * Copyright (c) 2024 Kai Sassnowski
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/roach-php/roach
 */

namespace RoachPHP\Tests\Downloader\Middleware;

use PHPUnit\Framework\TestCase;
use RoachPHP\Downloader\Middleware\RetryMiddleware;
use RoachPHP\Scheduling\ArrayRequestScheduler;
use RoachPHP\Scheduling\Timing\FakeClock;
use RoachPHP\Testing\Concerns\InteractsWithRequestsAndResponses;
use RoachPHP\Testing\FakeLogger;

final class RetryMiddlewareTest extends TestCase
{
    use InteractsWithRequestsAndResponses;

    private ArrayRequestScheduler $scheduler;

    private FakeLogger $logger;

    private RetryMiddleware $middleware;

    protected function setUp(): void
    {
        $this->scheduler = new ArrayRequestScheduler(new FakeClock());
        $this->logger = new FakeLogger();
        $this->middleware = new RetryMiddleware($this->scheduler, $this->logger);
    }

    public function testDoesNotRetrySuccessfulResponse(): void
    {
        $response = $this->makeResponse(status: 200);

        $result = $this->middleware->handleResponse($response);

        self::assertSame($response, $result);
        self::assertFalse($result->wasDropped());
        self::assertSame([], $this->scheduler->forceNextRequests(10));
    }

    public function testDoesNotRetryUnconfiguredStatus(): void
    {
        $response = $this->makeResponse(status: 404);

        $result = $this->middleware->handleResponse($response);

        self::assertSame($response, $result);
        self::assertFalse($result->wasDropped());
        self::assertSame([], $this->scheduler->forceNextRequests(10));
    }

    public function testRetriesConfiguredStatus(): void
    {
        $request = $this->makeRequest('https://example.com');
        $response = $this->makeResponse(request: $request, status: 503);
        $this->middleware->configure([
            'retryOnStatus' => [503],
            'maxRetries' => 2,
            'initialDelay' => 500,
            'multiplier' => 2.0,
        ]);

        $result = $this->middleware->handleResponse($response);

        self::assertTrue($result->wasDropped());

        $retriedRequests = $this->scheduler->forceNextRequests(10);
        self::assertCount(1, $retriedRequests);
        self::assertSame('https://example.com', $retriedRequests[0]->getUri());
        self::assertSame(1, $retriedRequests[0]->getMeta('roach_retry_count'));
        self::assertSame(500, $retriedRequests[0]->getOptions()['delay']);
        self::assertTrue($this->logger->messageWasLogged('info', '[RetryMiddleware] Retrying request'));
    }

    public function testStopsRetryingAtMaxRetries(): void
    {
        $request = $this->makeRequest('https://example.com')->withMeta('roach_retry_count', 3);
        $response = $this->makeResponse(request: $request, status: 500);
        $this->middleware->configure(['maxRetries' => 3]);

        $result = $this->middleware->handleResponse($response);

        self::assertSame($response, $result);
        self::assertFalse($result->wasDropped());
        self::assertSame([], $this->scheduler->forceNextRequests(10));
    }

    public function testUsesExponentialBackoff(): void
    {
        $request = $this->makeRequest('https://example.com')->withMeta('roach_retry_count', 2);
        $response = $this->makeResponse(request: $request, status: 500);
        $this->middleware->configure([
            'initialDelay' => 1000,
            'multiplier' => 2.0,
        ]);

        $this->middleware->handleResponse($response);

        $retriedRequests = $this->scheduler->forceNextRequests(10);

        self::assertSame(4000, $retriedRequests[0]->getOptions()['delay']);
    }
}
