<?php

declare(strict_types=1);

namespace Matchory\ServerTiming\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ArchitectureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function sourceFiles(): iterable
    {
        $src = (string) realpath(dirname(__DIR__) . '/src');
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $path = (string) $file->getRealPath();

                yield substr($path, strlen($src) + 1) => [$path];
            }
        }
    }

    #[Test]
    #[DataProvider('sourceFiles')]
    public function it_declares_strict_types(string $path): void
    {
        $this->assertStringContainsString('declare(strict_types=1);', (string) file_get_contents($path));
    }

    #[Test]
    #[DataProvider('sourceFiles')]
    public function it_resolves_the_contract_rather_than_the_concrete_class(string $path): void
    {
        $src = (string) realpath(dirname(__DIR__) . '/src');

        if (in_array($path, [$src . '/ServerTiming.php', $src . '/ServerTimingServiceProvider.php'], true)) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $this->assertStringNotContainsString(
            'use Matchory\ServerTiming\ServerTiming;',
            (string) file_get_contents($path),
        );
    }

    #[Test]
    #[DataProvider('sourceFiles')]
    public function it_makes_classes_final(string $path): void
    {
        if (!preg_match('/^(?:abstract |final |readonly )*class \w+/m', (string) file_get_contents($path), $class)) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $this->assertStringContainsString('final ', $class[0]);
    }
}
