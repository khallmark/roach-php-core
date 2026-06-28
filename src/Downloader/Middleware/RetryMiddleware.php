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

namespace RoachPHP\Downloader\Middleware;

use Psr\Log\LoggerInterface;
use RoachPHP\Http\Response;
use RoachPHP\Scheduling\RequestSchedulerInterface;
use RoachPHP\Support\Configurable;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RetryMiddleware implements ResponseMiddlewareInterface
{
    use Configurable;

    public function __construct(
        private readonly RequestSchedulerInterface $scheduler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handleResponse(Response $response): Response
    {
        $request = $response->getRequest();

        /** @var int $retryCount */
        $retryCount = $request->getMeta('roach_retry_count', 0);

        /** @var list<int> $retryOnStatus */
        $retryOnStatus = $this->option('retryOnStatus');

        /** @var int $maxRetries */
        $maxRetries = $this->option('maxRetries');

        if (!\in_array($response->getStatus(), $retryOnStatus, true) || $retryCount >= $maxRetries) {
            return $response;
        }

        $nextRetryCount = $retryCount + 1;
        $delay = $this->delayForAttempt($retryCount);
        $retryRequest = $request
            ->withMeta('roach_retry_count', $nextRetryCount)
            ->addOption('delay', $delay);

        $this->scheduler->schedule($retryRequest);

        $this->logger->info('[RetryMiddleware] Retrying request', [
            'uri' => $request->getUri(),
            'status' => $response->getStatus(),
            'retry_count' => $nextRetryCount,
            'delay_ms' => $delay,
        ]);

        return $response->drop('Request scheduled for retry');
    }

    private function delayForAttempt(int $retryCount): int
    {
        /** @var int $initialDelay */
        $initialDelay = $this->option('initialDelay');

        /** @var float $multiplier */
        $multiplier = $this->option('multiplier');

        return (int) \round($initialDelay * ($multiplier ** $retryCount));
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultOptions(): array
    {
        return [
            'retryOnStatus' => [500, 502, 503, 504],
            'maxRetries' => 3,
            'initialDelay' => 1000,
            'multiplier' => 2.0,
        ];
    }

    private function onAfterConfigured(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setRequired(['retryOnStatus', 'maxRetries', 'initialDelay', 'multiplier']);
        $resolver->setAllowedTypes('retryOnStatus', 'int[]');
        $resolver->setAllowedTypes('maxRetries', 'int');
        $resolver->setAllowedTypes('initialDelay', 'int');
        $resolver->setAllowedTypes('multiplier', ['float', 'int']);
        $resolver->setAllowedValues('maxRetries', static fn (int $value): bool => 0 <= $value);
        $resolver->setAllowedValues('initialDelay', static fn (int $value): bool => 0 <= $value);
        $resolver->setAllowedValues('multiplier', static fn (float|int $value): bool => 1 <= $value);

        /** @var array<string, mixed> $options */
        $options = [
            'retryOnStatus' => $this->option('retryOnStatus'),
            'maxRetries' => $this->option('maxRetries'),
            'initialDelay' => $this->option('initialDelay'),
            'multiplier' => $this->option('multiplier'),
        ];

        $resolver->resolve($options);
    }
}
