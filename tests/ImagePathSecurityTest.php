<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ImagePathSecurityTest extends TestCase
{
    // ---------------------------------------------------------------
    // is_safe_image_slug()
    // ---------------------------------------------------------------

    public static function safeSlugsProvider(): array
    {
        return [
            'simple slug'       => ['my-post'],
            'hello world'       => ['hello-world'],
            'alphanumeric'      => ['post123'],
            'single word'       => ['photos'],
            'numbers only'      => ['12345'],
            'dashes and digits' => ['2024-01-my-post'],
        ];
    }

    #[DataProvider('safeSlugsProvider')]
    public function testIsSafeImageSlugReturnsTrueForValidSlugs(string $slug): void
    {
        $this->assertTrue(is_safe_image_slug($slug));
    }

    public static function unsafeSlugsProvider(): array
    {
        return [
            'double dot traversal'         => ['../../etc'],
            'parent directory'             => ['../config'],
            'forward slash'                => ['foo/bar'],
            'backslash'                    => ['foo\\bar'],
            'bare double dot'              => ['..'],
            'empty string'                 => [''],
            'dot-dot at end'               => ['something/..'],
            'dot-dot in middle'            => ['a/../b'],
            'leading dot-dot with slash'   => ['../secret'],
            'single dot'                   => ['.'],
            'hidden file name'             => ['.htaccess'],
            'dot prefix slug'              => ['.hidden'],
            'null byte'                    => ["slug\0evil"],
        ];
    }

    #[DataProvider('unsafeSlugsProvider')]
    public function testIsSafeImageSlugReturnsFalseForTraversalAttempts(string $slug): void
    {
        $this->assertFalse(is_safe_image_slug($slug));
    }

    // ---------------------------------------------------------------
    // validate_image_path()
    // ---------------------------------------------------------------

    private string $tempBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempBase = sys_get_temp_dir() . '/pureblog_test_' . uniqid();
        mkdir($this->tempBase . '/images/my-post', 0755, true);
        touch($this->tempBase . '/images/my-post/photo.jpg');
        // Create a directory outside the base for escape tests
        mkdir($this->tempBase . '/outside', 0755, true);
        touch($this->tempBase . '/outside/secret.txt');
    }

    protected function tearDown(): void
    {
        // Clean up temp directories
        $this->removeDir($this->tempBase);
        parent::tearDown();
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testValidateImagePathReturnsTrueWhenTargetIsWithinBase(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/images/my-post/photo.jpg';

        $this->assertTrue(validate_image_path($base, $target));
    }

    public function testValidateImagePathReturnsTrueForSubdirectory(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/images/my-post';

        $this->assertTrue(validate_image_path($base, $target));
    }

    public function testValidateImagePathReturnsFalseWhenTargetEscapesBase(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/outside/secret.txt';

        $this->assertFalse(validate_image_path($base, $target));
    }

    public function testValidateImagePathReturnsFalseForNonExistentTarget(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/images/nonexistent/file.jpg';

        $this->assertFalse(validate_image_path($base, $target));
    }

    public function testValidateImagePathReturnsFalseForNonExistentBase(): void
    {
        $base = '/tmp/does_not_exist_' . uniqid();
        $target = $this->tempBase . '/images/my-post/photo.jpg';

        $this->assertFalse(validate_image_path($base, $target));
    }

    public function testValidateImagePathReturnsFalseWhenTargetIsBaseItself(): void
    {
        $base = $this->tempBase . '/images';
        // Target equals base -- should be false because str_starts_with checks for DIRECTORY_SEPARATOR suffix
        $this->assertFalse(validate_image_path($base, $base));
    }

    public function testValidateImagePathReturnsFalseForSiblingWithSharedPrefix(): void
    {
        $siblingDir = $this->tempBase . '/images-sibling';
        mkdir($siblingDir, 0755, true);
        touch($siblingDir . '/file.txt');

        $base = $this->tempBase . '/images';
        $target = $siblingDir . '/file.txt';

        $this->assertFalse(validate_image_path($base, $target));
    }

    public function testValidateImagePathRejectsSymlinkTraversal(): void
    {
        $linkPath = $this->tempBase . '/images/evil-link';
        $outsideTarget = $this->tempBase . '/outside/secret.txt';

        // Create a symlink inside images/ that points outside
        if (!@symlink($outsideTarget, $linkPath)) {
            $this->markTestSkipped('Cannot create symlinks in this environment.');
        }

        $base = $this->tempBase . '/images';

        // The symlink resolves to a path outside the base
        $this->assertFalse(validate_image_path($base, $linkPath));
    }
}
