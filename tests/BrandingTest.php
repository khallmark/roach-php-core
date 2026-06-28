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

namespace RoachPHP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BrandingTest extends TestCase
{
    public function testPublicPackageBrandingUsesRoach(): void
    {
        $composerJson = self::readFile('composer.json');
        $composer = \json_decode($composerJson, true, 512, \JSON_THROW_ON_ERROR);
        $readme = self::readFile('README.md');

        self::assertSame('roach-php/core', $composer['name']);
        self::assertSame('A complete web scraping toolkit for PHP', $composer['description']);
        self::assertArrayNotHasKey('replace', $composer);

        $authors = \array_column($composer['authors'], 'name');
        self::assertContains('Kai Sassnowski', $authors);
        self::assertNotContains('Neuro' . 'typic AI', $authors);

        self::assertStringContainsString('Roach is a complete web scraping toolkit for PHP.', $readme);
        self::assertStringContainsString('composer require roach-php/core', $readme);

        foreach ([$composerJson, $readme] as $publicBranding) {
            self::assertStringNotContainsString('Palm' . 'etto', $publicBranding);
            self::assertStringNotContainsString('palm' . 'etto', $publicBranding);
            self::assertStringNotContainsString('Neuro' . 'typic', $publicBranding);
            self::assertStringNotContainsString('neuro' . 'typic', $publicBranding);
        }
    }

    private static function readFile(string $path): string
    {
        $contents = \file_get_contents(__DIR__ . "/../{$path}");

        self::assertIsString($contents);

        return $contents;
    }
}
