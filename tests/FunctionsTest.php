<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class FunctionsTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    private function createTempMarkdownFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pureblog_test_') . '.md';
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    // =========================================================================
    // e() - HTML escaping
    // =========================================================================

    #[DataProvider('htmlEscapingProvider')]
    public function testEscapesHtmlSpecialChars(string $input, string $expected): void
    {
        $this->assertSame($expected, e($input));
    }

    public static function htmlEscapingProvider(): array
    {
        return [
            'empty string' => ['', ''],
            'plain text' => ['hello', 'hello'],
            'ampersand' => ['a&b', 'a&amp;b'],
            'double quotes' => ['"hello"', '&quot;hello&quot;'],
            'single quotes' => ["it's", "it&#039;s"],
            'less than' => ['<script>', '&lt;script&gt;'],
            'xss script tag' => ['<script>alert("xss")</script>', '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'],
            'xss img onerror' => ['<img onerror="alert(1)">', '&lt;img onerror=&quot;alert(1)&quot;&gt;'],
            'xss event handler' => ['<div onmouseover="steal()">', '&lt;div onmouseover=&quot;steal()&quot;&gt;'],
            'mixed special chars' => ['<a href="x&y">', '&lt;a href=&quot;x&amp;y&quot;&gt;'],
            'utf8 preserved' => ['Hello, World!', 'Hello, World!'],
            'utf8 japanese' => ['こんにちは', 'こんにちは'],
            'utf8 emoji' => ['Hello 🌍', 'Hello 🌍'],
            'already escaped should double escape' => ['&amp;', '&amp;amp;'],
        ];
    }

    // =========================================================================
    // slugify()
    // =========================================================================

    #[DataProvider('slugifyProvider')]
    public function testSlugify(string $input, string $expected): void
    {
        $this->assertSame($expected, slugify($input));
    }

    public static function slugifyProvider(): array
    {
        return [
            'simple string' => ['Hello World', 'hello-world'],
            'already slugified' => ['hello-world', 'hello-world'],
            'uppercase' => ['HELLO WORLD', 'hello-world'],
            'special characters' => ['Hello, World! @#$%', 'hello-world'],
            'multiple spaces' => ['hello   world', 'hello-world'],
            'leading trailing spaces' => ['  hello world  ', 'hello-world'],
            'leading trailing dashes' => ['--hello-world--', 'hello-world'],
            'numbers' => ['post 123', 'post-123'],
            'empty string' => ['', ''],
            'only special chars' => ['@#$%^&*()', ''],
            'unicode accented' => ["caf\u{00e9}", "caf\u{00e9}"],
            'tabs and newlines' => ["hello\tworld\nfoo", 'hello-world-foo'],
            'multiple dashes' => ['hello---world', 'hello-world'],
            'mixed dashes and spaces' => ['hello - world', 'hello-world'],
        ];
    }

    // =========================================================================
    // font_stack_css()
    // =========================================================================

    #[DataProvider('fontStackProvider')]
    public function testFontStackCss(string $input, string $expectedSubstring): void
    {
        $result = font_stack_css($input);
        $this->assertStringContainsString($expectedSubstring, $result);
    }

    public static function fontStackProvider(): array
    {
        return [
            'serif' => ['serif', 'Georgia'],
            'mono' => ['mono', 'monospace'],
            'sans' => ['sans', 'sans-serif'],
            'default for unknown' => ['unknown', 'sans-serif'],
            'default for empty' => ['', 'sans-serif'],
        ];
    }

    public function testFontStackCssExactValues(): void
    {
        $this->assertSame(
            'Georgia, Times, "Times New Roman", serif',
            font_stack_css('serif')
        );
        $this->assertSame(
            'ui-monospace, "Cascadia Code", "Source Code Pro", Menlo, Consolas, "DejaVu Sans Mono", monospace',
            font_stack_css('mono')
        );
        $this->assertSame(
            '-apple-system, BlinkMacSystemFont, "Avenir Next", Avenir, "Nimbus Sans L", Roboto, "Noto Sans", "Segoe UI", Arial, Helvetica, "Helvetica Neue", sans-serif',
            font_stack_css('sans')
        );
    }

    // =========================================================================
    // default_config()
    // =========================================================================

    public function testDefaultConfigReturnsArray(): void
    {
        $config = default_config();
        $this->assertIsArray($config);
    }

    public function testDefaultConfigContainsRequiredKeys(): void
    {
        $config = default_config();
        $requiredKeys = [
            'site_title',
            'site_tagline',
            'site_description',
            'site_email',
            'custom_nav',
            'custom_routes',
            'head_inject_page',
            'head_inject_post',
            'footer_inject_page',
            'footer_inject_post',
            'posts_per_page',
            'homepage_slug',
            'blog_page_slug',
            'hide_homepage_title',
            'hide_blog_page_title',
            'base_url',
            'timezone',
            'date_format',
            'admin_username',
            'admin_password_hash',
            'cache',
            'theme',
            'assets',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Missing key: $key");
        }
    }

    public function testDefaultConfigValues(): void
    {
        $config = default_config();
        $this->assertSame('My Blog', $config['site_title']);
        $this->assertSame(20, $config['posts_per_page']);
        $this->assertSame('F j, Y', $config['date_format']);
        $this->assertTrue($config['hide_homepage_title']);
        $this->assertTrue($config['hide_blog_page_title']);
        $this->assertSame('', $config['admin_username']);
        $this->assertSame('', $config['admin_password_hash']);
    }

    public function testDefaultConfigThemeKeys(): void
    {
        $config = default_config();
        $theme = $config['theme'];
        $this->assertIsArray($theme);
        $this->assertArrayHasKey('color_mode', $theme);
        $this->assertArrayHasKey('font_stack', $theme);
        $this->assertArrayHasKey('background_color', $theme);
        $this->assertArrayHasKey('text_color', $theme);
        $this->assertArrayHasKey('accent_color', $theme);
        $this->assertArrayHasKey('post_list_layout', $theme);
        $this->assertSame('sans', $theme['font_stack']);
        $this->assertSame('auto', $theme['color_mode']);
    }

    public function testDefaultConfigCacheKeys(): void
    {
        $config = default_config();
        $cache = $config['cache'];
        $this->assertIsArray($cache);
        $this->assertFalse($cache['enabled']);
        $this->assertSame(3600, $cache['rss_ttl']);
    }

    public function testDefaultConfigAssetsKeys(): void
    {
        $config = default_config();
        $assets = $config['assets'];
        $this->assertIsArray($assets);
        $this->assertArrayHasKey('favicon', $assets);
        $this->assertArrayHasKey('og_image', $assets);
        $this->assertArrayHasKey('og_image_preferred', $assets);
    }

    // =========================================================================
    // normalize_date_value()
    // =========================================================================

    #[DataProvider('normalizeDateProvider')]
    public function testNormalizeDateValue(string $input, ?string $expected): void
    {
        $this->assertSame($expected, normalize_date_value($input));
    }

    public static function normalizeDateProvider(): array
    {
        return [
            'empty string' => ['', null],
            'Y-m-d H:i format' => ['2024-06-15 10:30', '2024-06-15 10:30'],
            'Y-m-d H:i:s format' => ['2024-06-15 10:30:45', '2024-06-15 10:30'],
            'ISO 8601 with Z' => ['2024-06-15T10:30:00Z', '2024-06-15 10:30'],
            'ISO 8601 with microseconds and Z' => ['2024-06-15T10:30:00.000000Z', '2024-06-15 10:30'],
            'ISO 8601 with offset' => ['2024-06-15T10:30:00+05:00', '2024-06-15 10:30'],
            'ISO 8601 with microseconds and offset' => ['2024-06-15T10:30:00.000000+05:00', '2024-06-15 10:30'],
            'strtotime fallback date only' => ['2024-06-15', '2024-06-15 00:00'],
            'invalid string' => ['not-a-date', null],
            'midnight' => ['2024-01-01 00:00', '2024-01-01 00:00'],
            'end of day' => ['2024-12-31 23:59', '2024-12-31 23:59'],
        ];
    }

    // =========================================================================
    // parse_post_file()
    // =========================================================================

    public function testParsePostFileBasicFrontMatter(): void
    {
        $content = <<<'MD'
---
title: Hello World
slug: hello-world
date: 2024-06-15 10:30
status: published
---

This is the body.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame('Hello World', $result['front_matter']['title']);
        $this->assertSame('hello-world', $result['front_matter']['slug']);
        $this->assertSame('2024-06-15 10:30', $result['front_matter']['date']);
        $this->assertSame('published', $result['front_matter']['status']);
        $this->assertSame('This is the body.', trim($result['content']));
    }

    public function testParsePostFileInlineTags(): void
    {
        $content = <<<'MD'
---
title: Tagged Post
slug: tagged-post
tags: [php, testing, web]
---

Content here.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame(['php', 'testing', 'web'], $result['front_matter']['tags']);
    }

    public function testParsePostFileListTags(): void
    {
        $content = <<<'MD'
---
title: Tagged Post
slug: tagged-post
tags:
  - php
  - testing
  - web
---

Content here.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame(['php', 'testing', 'web'], $result['front_matter']['tags']);
    }

    public function testParsePostFileEmptyFile(): void
    {
        $path = $this->createTempMarkdownFile('');
        $result = parse_post_file($path);

        $this->assertSame([], $result['front_matter']);
        $this->assertSame('', $result['content']);
    }

    public function testParsePostFileNoFrontMatter(): void
    {
        $content = "Just some markdown content without front matter.\n";
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame([], $result['front_matter']);
        $this->assertStringContainsString('Just some markdown content', $result['content']);
    }

    public function testParsePostFileCategoriesMergedIntoTags(): void
    {
        $content = <<<'MD'
---
title: Category Post
slug: cat-post
tags: [php, web]
categories: [web, backend]
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $tags = $result['front_matter']['tags'];
        $this->assertContains('php', $tags);
        $this->assertContains('web', $tags);
        $this->assertContains('backend', $tags);
        // 'web' should be deduplicated
        $this->assertCount(3, $tags);
    }

    public function testParsePostFileCategoriesAsListMergedIntoTags(): void
    {
        $content = <<<'MD'
---
title: Category Post
slug: cat-post
tags: [php]
categories:
  - backend
  - devops
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $tags = $result['front_matter']['tags'];
        $this->assertContains('php', $tags);
        $this->assertContains('backend', $tags);
        $this->assertContains('devops', $tags);
    }

    public function testParsePostFileIncludeInNavTrue(): void
    {
        $content = <<<'MD'
---
title: Nav Page
slug: nav-page
include_in_nav: true
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertTrue($result['front_matter']['include_in_nav']);
    }

    public function testParsePostFileIncludeInNavFalse(): void
    {
        $content = <<<'MD'
---
title: Hidden Page
slug: hidden-page
include_in_nav: false
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertFalse($result['front_matter']['include_in_nav']);
    }

    public function testParsePostFileDateNormalization(): void
    {
        $content = <<<'MD'
---
title: Date Post
slug: date-post
date: "2024-06-15T10:30:00Z"
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame('2024-06-15 10:30', $result['front_matter']['date']);
    }

    public function testParsePostFileDescription(): void
    {
        $content = <<<'MD'
---
title: Described Post
slug: described-post
description: A short description of the post
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame('A short description of the post', $result['front_matter']['description']);
    }

    public function testParsePostFileNonexistentFile(): void
    {
        @$result = parse_post_file('/tmp/nonexistent_pureblog_test_file_xyz.md');
        $this->assertSame([], $result['front_matter']);
        $this->assertSame('', $result['content']);
    }

    public function testParsePostFileEmptyTagsInline(): void
    {
        $content = <<<'MD'
---
title: No Tags
slug: no-tags
tags: []
---

Content.
MD;
        $path = $this->createTempMarkdownFile($content);
        $result = parse_post_file($path);

        $this->assertSame([], $result['front_matter']['tags']);
    }

    // =========================================================================
    // site_timezone_identifier()
    // =========================================================================

    public function testSiteTimezoneIdentifierWithValidTimezone(): void
    {
        $config = ['timezone' => 'America/New_York'];
        $this->assertSame('America/New_York', site_timezone_identifier($config));
    }

    public function testSiteTimezoneIdentifierWithInvalidTimezone(): void
    {
        $config = ['timezone' => 'Invalid/Timezone'];
        $this->assertSame(date_default_timezone_get(), site_timezone_identifier($config));
    }

    public function testSiteTimezoneIdentifierWithEmptyTimezone(): void
    {
        $config = ['timezone' => ''];
        $this->assertSame(date_default_timezone_get(), site_timezone_identifier($config));
    }

    public function testSiteTimezoneIdentifierWithMissingKey(): void
    {
        $config = [];
        $this->assertSame(date_default_timezone_get(), site_timezone_identifier($config));
    }

    public function testSiteTimezoneIdentifierUtc(): void
    {
        $config = ['timezone' => 'UTC'];
        $this->assertSame('UTC', site_timezone_identifier($config));
    }

    // =========================================================================
    // site_date_format()
    // =========================================================================

    public function testSiteDateFormatWithCustomFormat(): void
    {
        $config = ['date_format' => 'Y-m-d'];
        $this->assertSame('Y-m-d', site_date_format($config));
    }

    public function testSiteDateFormatWithEmptyFormat(): void
    {
        $config = ['date_format' => ''];
        $this->assertSame('F j, Y', site_date_format($config));
    }

    public function testSiteDateFormatWithMissingKey(): void
    {
        $config = [];
        $this->assertSame('F j, Y', site_date_format($config));
    }

    public function testSiteDateFormatWithWhitespaceOnly(): void
    {
        $config = ['date_format' => '   '];
        $this->assertSame('F j, Y', site_date_format($config));
    }

    // =========================================================================
    // format_post_date_for_display()
    // =========================================================================

    public function testFormatPostDateForDisplayWithValidDate(): void
    {
        $config = ['date_format' => 'Y-m-d', 'timezone' => 'UTC'];
        $result = format_post_date_for_display('2024-06-15 10:30', $config);
        $this->assertSame('2024-06-15', $result);
    }

    public function testFormatPostDateForDisplayWithNull(): void
    {
        $config = ['date_format' => 'Y-m-d', 'timezone' => 'UTC'];
        $result = format_post_date_for_display(null, $config);
        $this->assertSame('', $result);
    }

    public function testFormatPostDateForDisplayWithEmptyString(): void
    {
        $config = ['date_format' => 'Y-m-d', 'timezone' => 'UTC'];
        $result = format_post_date_for_display('', $config);
        $this->assertSame('', $result);
    }

    public function testFormatPostDateForDisplayDefaultFormat(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = format_post_date_for_display('2024-06-15 10:30', $config);
        // Default format is 'F j, Y'
        $this->assertSame('June 15, 2024', $result);
    }

    public function testFormatPostDateForDisplayWithTimezone(): void
    {
        $config = ['date_format' => 'Y-m-d H:i', 'timezone' => 'America/New_York'];
        $result = format_post_date_for_display('2024-06-15 10:30', $config);
        $this->assertSame('2024-06-15 10:30', $result);
    }

    // =========================================================================
    // format_post_date_for_rss()
    // =========================================================================

    public function testFormatPostDateForRssWithValidDate(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = format_post_date_for_rss('2024-06-15 10:30', $config);
        // DATE_RSS format: e.g. "Sat, 15 Jun 2024 10:30:00 +0000"
        $this->assertStringContainsString('15 Jun 2024', $result);
        $this->assertStringContainsString('10:30:00', $result);
    }

    public function testFormatPostDateForRssWithNull(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = format_post_date_for_rss(null, $config);
        // Should return current time in RSS format -- just verify it's a valid RSS date
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/\w{3}, \d{2} \w{3} \d{4}/', $result);
    }

    public function testFormatPostDateForRssWithEmptyString(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = format_post_date_for_rss('', $config);
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/\w{3}, \d{2} \w{3} \d{4}/', $result);
    }

    // =========================================================================
    // get_excerpt()
    // =========================================================================

    #[DataProvider('excerptProvider')]
    public function testGetExcerpt(string $markdown, int $length, string $expectedSubstring, ?string $shouldNotContain = null): void
    {
        $result = get_excerpt($markdown, $length);
        $this->assertStringContainsString($expectedSubstring, $result);
        if ($shouldNotContain !== null) {
            $this->assertStringNotContainsString($shouldNotContain, $result);
        }
    }

    public static function excerptProvider(): array
    {
        return [
            'plain text' => ['Hello World', 200, 'Hello World'],
            'strips markdown bold' => ['**bold text** here', 200, 'bold text here'],
            'strips markdown italic' => ['*italic text* here', 200, 'italic text here'],
            'strips images' => ['![alt](http://example.com/img.png) text', 200, 'text', '!['],
            'converts links to text' => ['[link text](http://example.com) after', 200, 'link text after'],
            'strips code blocks' => ["```php\n\$code = true;\n``` after code", 200, 'after code', '$code'],
            'strips inline code' => ['Use `code()` function', 200, 'Use function'],
            'strips headers' => ['## Header\nContent', 200, 'Content'],
        ];
    }

    public function testGetExcerptTruncatesAtLength(): void
    {
        $longText = str_repeat('word ', 100);
        $result = get_excerpt($longText, 50);
        $this->assertLessThanOrEqual(53, mb_strlen($result)); // 50 + '...'
        $this->assertStringEndsWith('...', $result);
    }

    public function testGetExcerptDoesNotTruncateShortText(): void
    {
        $result = get_excerpt('Short text.', 200);
        $this->assertSame('Short text.', $result);
        $this->assertStringEndsNotWith('...', $result);
    }

    public function testGetExcerptHandlesMoreTag(): void
    {
        $markdown = "Before the fold.<!--more-->After the fold with much more content.";
        $result = get_excerpt($markdown, 200);
        $this->assertStringContainsString('Before the fold.', $result);
        $this->assertStringNotContainsString('After the fold', $result);
    }

    public function testGetExcerptEmptyString(): void
    {
        $this->assertSame('', get_excerpt('', 200));
    }

    public function testGetExcerptDefaultLength(): void
    {
        $longText = str_repeat('a ', 200);
        $result = get_excerpt($longText);
        // Default length is 200
        $this->assertLessThanOrEqual(203, mb_strlen($result));
    }

    // =========================================================================
    // detect_pureblog_version()
    // =========================================================================

    public function testDetectPureblogVersionReadsVersionFile(): void
    {
        // The VERSION file exists in the project root
        $version = detect_pureblog_version();
        $this->assertSame('1.8.0', $version);
    }

    // =========================================================================
    // parse_post_datetime_with_timezone()
    // =========================================================================

    public function testParsePostDatetimeWithTimezoneValidDate(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = parse_post_datetime_with_timezone('2024-06-15 10:30', $config);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15', $result->format('Y-m-d'));
        $this->assertSame('10:30', $result->format('H:i'));
    }

    public function testParsePostDatetimeWithTimezoneNull(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = parse_post_datetime_with_timezone(null, $config);
        $this->assertNull($result);
    }

    public function testParsePostDatetimeWithTimezoneEmptyString(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = parse_post_datetime_with_timezone('', $config);
        $this->assertNull($result);
    }

    public function testParsePostDatetimeWithTimezoneInvalidString(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = parse_post_datetime_with_timezone('not-a-date', $config);
        $this->assertNull($result);
    }

    public function testParsePostDatetimeWithTimezoneSecondsFormat(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = parse_post_datetime_with_timezone('2024-06-15 10:30:45', $config);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15', $result->format('Y-m-d'));
    }

    public function testParsePostDatetimeWithTimezoneIso8601(): void
    {
        $config = ['timezone' => 'America/New_York'];
        $result = parse_post_datetime_with_timezone('2024-06-15T10:30:00Z', $config);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('America/New_York', $result->getTimezone()->getName());
    }

    public function testParsePostDatetimeWithTimezoneStrtotimeFallback(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = parse_post_datetime_with_timezone('June 15, 2024', $config);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-06-15', $result->format('Y-m-d'));
    }

    public function testParsePostDatetimeWithTimezoneAppliesConfigTimezone(): void
    {
        $config = ['timezone' => 'Asia/Tokyo'];
        $result = parse_post_datetime_with_timezone('2024-06-15 10:30', $config);
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('Asia/Tokyo', $result->getTimezone()->getName());
    }

    // =========================================================================
    // normalize_tag()
    // =========================================================================

    public function testNormalizeTagDelegatesToSlugify(): void
    {
        $this->assertSame('hello-world', normalize_tag('Hello World'));
        $this->assertSame('php', normalize_tag('PHP'));
        $this->assertSame('', normalize_tag(''));
    }

    // =========================================================================
    // parse_custom_nav()
    // =========================================================================

    public function testParseCustomNavValidEntries(): void
    {
        $raw = "About|/about\nBlog|/blog";
        $result = parse_custom_nav($raw);
        $this->assertCount(2, $result);
        $this->assertSame(['label' => 'About', 'url' => '/about'], $result[0]);
        $this->assertSame(['label' => 'Blog', 'url' => '/blog'], $result[1]);
    }

    public function testParseCustomNavEmptyString(): void
    {
        $this->assertSame([], parse_custom_nav(''));
    }

    public function testParseCustomNavSkipsInvalidLines(): void
    {
        $raw = "About|/about\ninvalid line\n|/no-label\nBlog|";
        $result = parse_custom_nav($raw);
        $this->assertCount(1, $result);
        $this->assertSame('About', $result[0]['label']);
    }

    // =========================================================================
    // parse_custom_routes()
    // =========================================================================

    public function testParseCustomRoutesValidEntries(): void
    {
        $raw = "/custom|template.php\n/other|other.php";
        $result = parse_custom_routes($raw);
        $this->assertCount(2, $result);
        $this->assertSame('/custom', $result[0]['path']);
        $this->assertSame('template.php', $result[0]['target']);
    }

    public function testParseCustomRoutesNormalizesLeadingSlash(): void
    {
        $raw = "custom|template.php";
        $result = parse_custom_routes($raw);
        $this->assertSame('/custom', $result[0]['path']);
    }

    public function testParseCustomRoutesRejectsDuplicates(): void
    {
        $raw = "/custom|template.php\n/custom|other.php";
        $result = parse_custom_routes($raw);
        $this->assertCount(1, $result);
    }

    public function testParseCustomRoutesRejectsDoubleDots(): void
    {
        $raw = "/foo/../bar|template.php";
        $result = parse_custom_routes($raw);
        $this->assertCount(0, $result);
    }

    public function testParseCustomRoutesRejectsDoubleSlashes(): void
    {
        $raw = "/foo//bar|template.php";
        $result = parse_custom_routes($raw);
        $this->assertCount(0, $result);
    }

    public function testParseCustomRoutesSkipsComments(): void
    {
        $raw = "# a comment\n/valid|template.php";
        $result = parse_custom_routes($raw);
        $this->assertCount(1, $result);
        $this->assertSame('/valid', $result[0]['path']);
    }

    public function testParseCustomRoutesTrimsTrailingSlash(): void
    {
        $raw = "/custom/|template.php";
        $result = parse_custom_routes($raw);
        $this->assertSame('/custom', $result[0]['path']);
    }

    // =========================================================================
    // paginate_posts()
    // =========================================================================

    public function testPaginatePostsBasic(): void
    {
        $posts = array_fill(0, 25, ['title' => 'test']);
        $result = paginate_posts($posts, 10, 1);

        $this->assertCount(10, $result['posts']);
        $this->assertSame(25, $result['totalPosts']);
        $this->assertSame(3, $result['totalPages']);
        $this->assertSame(1, $result['currentPage']);
    }

    public function testPaginatePostsLastPage(): void
    {
        $posts = array_fill(0, 25, ['title' => 'test']);
        $result = paginate_posts($posts, 10, 3);

        $this->assertCount(5, $result['posts']);
        $this->assertSame(3, $result['currentPage']);
    }

    public function testPaginatePostsEmptyArray(): void
    {
        $result = paginate_posts([], 10, 1);

        $this->assertCount(0, $result['posts']);
        $this->assertSame(0, $result['totalPosts']);
        $this->assertSame(1, $result['totalPages']);
    }

    public function testPaginatePostsMinPerPage(): void
    {
        $posts = array_fill(0, 5, ['title' => 'test']);
        $result = paginate_posts($posts, 0, 1);

        // perPage should be clamped to at least 1
        $this->assertSame(5, $result['totalPages']);
    }

    // =========================================================================
    // get_excerpt() additional edge cases
    // =========================================================================

    public function testGetExcerptStripsMultipleMarkdownElements(): void
    {
        $markdown = "## Title\n\n**Bold** and *italic* with [a link](http://example.com)\n\n> blockquote\n\n- list item";
        $result = get_excerpt($markdown, 200);
        // Should be plain text without markdown
        $this->assertStringNotContainsString('##', $result);
        $this->assertStringNotContainsString('**', $result);
        $this->assertStringNotContainsString('[', $result);
        $this->assertStringContainsString('Bold', $result);
        $this->assertStringContainsString('italic', $result);
        $this->assertStringContainsString('a link', $result);
    }

    // =========================================================================
    // get_contextual_inject()
    // =========================================================================

    public function testGetContextualInjectReturnsPostInjectForPostView(): void
    {
        $config = [
            'head_inject_post' => '<script>post</script>',
            'head_inject_page' => '<script>page</script>',
        ];
        $context = ['post' => ['title' => 'Test']];
        $this->assertSame('<script>post</script>', get_contextual_inject($config, 'head', $context));
    }

    public function testGetContextualInjectReturnsPageInjectForNonPostView(): void
    {
        $config = [
            'head_inject_post' => '<script>post</script>',
            'head_inject_page' => '<script>page</script>',
        ];
        $context = [];
        $this->assertSame('<script>page</script>', get_contextual_inject($config, 'head', $context));
    }

    public function testGetContextualInjectFooterRegion(): void
    {
        $config = [
            'footer_inject_post' => 'post-footer',
            'footer_inject_page' => 'page-footer',
        ];
        $this->assertSame('page-footer', get_contextual_inject($config, 'footer', []));
        $this->assertSame('post-footer', get_contextual_inject($config, 'footer', ['post' => ['title' => 'X']]));
    }

    // =========================================================================
    // filter_posts_by_query()
    // =========================================================================

    public function testFilterPostsByQueryMatchesTitle(): void
    {
        $posts = [
            ['title' => 'PHP Tips', 'description' => '', 'tags' => []],
            ['title' => 'Python Guide', 'description' => '', 'tags' => []],
        ];
        $result = filter_posts_by_query($posts, 'PHP');
        $this->assertCount(1, $result);
        $this->assertSame('PHP Tips', $result[0]['title']);
    }

    public function testFilterPostsByQueryMatchesTags(): void
    {
        $posts = [
            ['title' => 'A Post', 'description' => '', 'tags' => ['laravel', 'php']],
            ['title' => 'Another', 'description' => '', 'tags' => ['python']],
        ];
        $result = filter_posts_by_query($posts, 'laravel');
        $this->assertCount(1, $result);
    }

    public function testFilterPostsByQueryEmptyQuery(): void
    {
        $posts = [
            ['title' => 'A Post', 'description' => '', 'tags' => []],
        ];
        $result = filter_posts_by_query($posts, '');
        $this->assertCount(1, $result);
    }

    public function testFilterPostsByQueryCaseInsensitive(): void
    {
        $posts = [
            ['title' => 'PHP Tips', 'description' => '', 'tags' => []],
        ];
        $result = filter_posts_by_query($posts, 'php tips');
        $this->assertCount(1, $result);
    }

    // =========================================================================
    // resolve_template_file() - purely path-based, no filesystem side effects
    // =========================================================================

    public function testResolveTemplateFileRejectsEmptyName(): void
    {
        $this->assertNull(resolve_template_file('', '/tmp/user', '/tmp/core'));
    }

    public function testResolveTemplateFileRejectsUnsafeCharacters(): void
    {
        $this->assertNull(resolve_template_file('../etc/passwd', '/tmp/user', '/tmp/core'));
        $this->assertNull(resolve_template_file('foo/bar', '/tmp/user', '/tmp/core'));
        $this->assertNull(resolve_template_file('file.php', '/tmp/user', '/tmp/core'));
    }

    // =========================================================================
    // protect/restore fenced code blocks and inline code spans
    // =========================================================================

    public function testProtectAndRestoreFencedCodeBlocks(): void
    {
        $markdown = "before\n```php\n\$x = 1;\n```\nafter";
        $blocks = [];
        $protected = protect_fenced_code_blocks($markdown, $blocks);

        $this->assertStringNotContainsString('```', $protected);
        $this->assertStringContainsString('before', $protected);
        $this->assertStringContainsString('after', $protected);
        $this->assertCount(1, $blocks);

        $restored = restore_fenced_code_blocks($protected, $blocks);
        $this->assertSame($markdown, $restored);
    }

    public function testProtectAndRestoreInlineCodeSpans(): void
    {
        $markdown = "Use `code()` and `other()` here";
        $spans = [];
        $protected = protect_inline_code_spans($markdown, $spans);

        $this->assertStringNotContainsString('`', $protected);
        $this->assertCount(2, $spans);

        $restored = restore_inline_code_spans($protected, $spans);
        $this->assertSame($markdown, $restored);
    }

    public function testRestoreFencedCodeBlocksWithEmptyArray(): void
    {
        $markdown = "nothing to restore";
        $this->assertSame($markdown, restore_fenced_code_blocks($markdown, []));
    }

    public function testRestoreInlineCodeSpansWithEmptyArray(): void
    {
        $markdown = "nothing to restore";
        $this->assertSame($markdown, restore_inline_code_spans($markdown, []));
    }

    // =========================================================================
    // render_tag_links()
    // =========================================================================

    public function testRenderTagLinksWithTags(): void
    {
        $result = render_tag_links(['PHP', 'Web']);
        $this->assertStringContainsString('<a href="/tag/php">PHP</a>', $result);
        $this->assertStringContainsString('<a href="/tag/web">Web</a>', $result);
        $this->assertStringContainsString(', ', $result);
    }

    public function testRenderTagLinksEmpty(): void
    {
        $this->assertSame('', render_tag_links([]));
    }

    public function testRenderTagLinksFiltersEmptyStrings(): void
    {
        $result = render_tag_links(['PHP', '', '  ', 'Web']);
        // Should only have 2 links
        $this->assertSame(2, substr_count($result, '<a '));
    }

    // =========================================================================
    // current_site_datetime_for_storage()
    // =========================================================================

    public function testCurrentSiteDatetimeForStorageFormat(): void
    {
        $config = ['timezone' => 'UTC'];
        $result = current_site_datetime_for_storage($config);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $result);
    }

    public function testCurrentSiteDatetimeForStorageUsesConfigTimezone(): void
    {
        // This just verifies it returns a valid datetime string; timezone
        // correctness is implicitly tested via site_timezone_object.
        $config = ['timezone' => 'Pacific/Auckland'];
        $result = current_site_datetime_for_storage($config);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $result);
    }
}
