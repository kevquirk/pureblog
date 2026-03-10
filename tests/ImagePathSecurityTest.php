<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for image path security helpers: is_safe_image_slug() and validate_image_path().
 *
 * These functions guard against path traversal attacks in the image
 * upload and delete workflows (admin/upload-image.php, admin/delete-image.php).
 */
class ImagePathSecurityTest extends TestCase
{
    // ---------------------------------------------------------------
    // is_safe_image_slug()
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
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

    /**
     * Verify that well-formed slugs (alphanumeric, hyphens, underscores) are accepted.
     */
    #[DataProvider('safeSlugsProvider')]
    public function testIsSafeImageSlugReturnsTrueForValidSlugs(string $slug): void
    {
        $this->assertTrue(is_safe_image_slug($slug));
    }

    /**
     * @return array<string, array{0: string}>
     */
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

    /**
     * Verify that slugs containing traversal sequences, path separators,
     * dot-prefixed names, or null bytes are rejected.
     */
    #[DataProvider('unsafeSlugsProvider')]
    public function testIsSafeImageSlugReturnsFalseForTraversalAttempts(string $slug): void
    {
        $this->assertFalse(is_safe_image_slug($slug));
    }

    // ---------------------------------------------------------------
    // validate_image_path()
    // ---------------------------------------------------------------

    private string $tempBase;

    /**
     * Create a temporary directory tree for path validation tests:
     *   {tempBase}/images/my-post/photo.jpg  — valid target
     *   {tempBase}/outside/secret.txt        — target outside the base
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tempBase = sys_get_temp_dir() . '/pureblog_test_' . uniqid();
        mkdir($this->tempBase . '/images/my-post', 0755, true);
        touch($this->tempBase . '/images/my-post/photo.jpg');
        mkdir($this->tempBase . '/outside', 0755, true);
        touch($this->tempBase . '/outside/secret.txt');
    }

    /**
     * Remove the temporary directory tree after each test.
     */
    protected function tearDown(): void
    {
        $this->removeDir($this->tempBase);
        parent::tearDown();
    }

    /**
     * Recursively remove a directory and all its contents.
     */
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

    /**
     * A file nested inside the base directory should be accepted.
     */
    public function testValidateImagePathReturnsTrueWhenTargetIsWithinBase(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/images/my-post/photo.jpg';

        $this->assertTrue(validate_image_path($base, $target));
    }

    /**
     * A subdirectory of the base should be accepted (used for folder-level checks).
     */
    public function testValidateImagePathReturnsTrueForSubdirectory(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/images/my-post';

        $this->assertTrue(validate_image_path($base, $target));
    }

    /**
     * A target outside the base directory must be rejected.
     */
    public function testValidateImagePathReturnsFalseWhenTargetEscapesBase(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/outside/secret.txt';

        $this->assertFalse(validate_image_path($base, $target));
    }

    /**
     * A non-existent target path must be rejected (realpath returns false).
     */
    public function testValidateImagePathReturnsFalseForNonExistentTarget(): void
    {
        $base = $this->tempBase . '/images';
        $target = $this->tempBase . '/images/nonexistent/file.jpg';

        $this->assertFalse(validate_image_path($base, $target));
    }

    /**
     * A non-existent base directory must be rejected.
     */
    public function testValidateImagePathReturnsFalseForNonExistentBase(): void
    {
        $base = '/tmp/does_not_exist_' . uniqid();
        $target = $this->tempBase . '/images/my-post/photo.jpg';

        $this->assertFalse(validate_image_path($base, $target));
    }

    /**
     * The base directory itself must not be treated as a valid target.
     * The DIRECTORY_SEPARATOR suffix in the str_starts_with check prevents this.
     */
    public function testValidateImagePathReturnsFalseWhenTargetIsBaseItself(): void
    {
        $base = $this->tempBase . '/images';
        $this->assertFalse(validate_image_path($base, $base));
    }

    /**
     * A sibling directory whose name shares a prefix with the base
     * (e.g. "images-sibling" vs "images") must be rejected. The
     * DIRECTORY_SEPARATOR suffix prevents false prefix matches.
     */
    public function testValidateImagePathReturnsFalseForSiblingWithSharedPrefix(): void
    {
        $siblingDir = $this->tempBase . '/images-sibling';
        mkdir($siblingDir, 0755, true);
        touch($siblingDir . '/file.txt');

        $base = $this->tempBase . '/images';
        $target = $siblingDir . '/file.txt';

        $this->assertFalse(validate_image_path($base, $target));
    }

    /**
     * A symlink inside the base that points outside must be rejected,
     * because realpath() resolves to the true target location.
     */
    public function testValidateImagePathRejectsSymlinkTraversal(): void
    {
        $linkPath = $this->tempBase . '/images/evil-link';
        $outsideTarget = $this->tempBase . '/outside/secret.txt';

        if (!@symlink($outsideTarget, $linkPath)) {
            $this->markTestSkipped('Cannot create symlinks in this environment.');
        }

        $base = $this->tempBase . '/images';

        $this->assertFalse(validate_image_path($base, $linkPath));
    }
}
