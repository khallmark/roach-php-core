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

namespace RoachPHP\Tests\Shell\Resolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoachPHP\Shell\Resolver\DefaultNamespaceResolverDecorator;
use RoachPHP\Shell\Resolver\FakeNamespaceResolver;
use RoachPHP\Tests\Fixtures\TestSpider;

/**
 * @internal
 */
final class DefaultNamespaceResolverDecoratorTest extends TestCase
{
    public function testPassInputThroughUnchangedIfItAlreadyPointsToExistingClass(): void
    {
        $result = self::getResolver('::different-default-namespace::')->resolveSpiderNamespace(TestSpider::class);

        self::assertSame(TestSpider::class, $result);
    }

    #[DataProvider('prependNamespaceProvider')]
    public function testPrependsDefaultNamespaceIfPassedClassDoesNotExist(string $spiderName): void
    {
        $result = self::getResolver()->resolveSpiderNamespace($spiderName);

        self::assertSame('RoachPHP\Tests\Fixtures\\' . $spiderName, $result);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function prependNamespaceProvider(): iterable
    {
        yield from [
            'only class name' => [
                'TestSpider',
            ],
            'relative namespace' => [
                'Derp\TestSpider',
            ],
        ];
    }

    #[DataProvider('defaultNamespaceProvider')]
    public function testNormalizesDefaultNamespace(string $nonNormalizedNamespace): void
    {
        $result = self::getResolver($nonNormalizedNamespace)->resolveSpiderNamespace('TestSpider');

        self::assertSame('RoachPHP\Tests\Fixtures\TestSpider', $result);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function defaultNamespaceProvider(): iterable
    {
        yield from [
            'leading backslashes' => [
                '\RoachPHP\Tests\Fixtures',
            ],
            'trailing backslashes' => [
                'RoachPHP\Tests\Fixtures\\',
            ],
            'trailing spaces' => [
                'RoachPHP\Tests\Fixtures ',
            ],
            'leading spaces' => [
                ' RoachPHP\Tests\Fixtures',
            ],
        ];
    }

    #[DataProvider('spiderNameProvider')]
    public function testNormalizesProvidedSpiderName(string $nonNormalizedSpiderName): void
    {
        $result = self::getResolver()->resolveSpiderNamespace($nonNormalizedSpiderName);

        self::assertSame('RoachPHP\Tests\Fixtures\TestSpider', $result);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function spiderNameProvider(): iterable
    {
        yield from [
            'leading spaces' => [
                ' TestSpider',
            ],
            'trailing spaces' => [
                'TestSpider ',
            ],
        ];
    }

    public function testTreatsLeadingBackslashesAsAbsolutePathAndReturnsItAsIs(): void
    {
        $result = self::getResolver()->resolveSpiderNamespace('\Test\Spider');

        self::assertSame('\Test\Spider', $result);
    }

    public function testDoesNotPrependDefaultNamespaceIfInputAlreadyStartsWithIt(): void
    {
        $result = self::getResolver('::default-namespace::')->resolveSpiderNamespace('::default-namespace::\Spider');

        self::assertSame('::default-namespace::\Spider', $result);
    }

    private static function getResolver(string $defaultNamespace = 'RoachPHP\Tests\Fixtures'): DefaultNamespaceResolverDecorator
    {
        return new DefaultNamespaceResolverDecorator(
            new FakeNamespaceResolver(),
            $defaultNamespace,
        );
    }
}
