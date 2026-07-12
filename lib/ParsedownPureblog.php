<?php

/**
 * ParsedownPureblog extends ParsedownExtra with paragraph-level attribute support.
 *
 * Usage in Markdown:
 *   This is a notice. {.notice .warning}
 *   Renders as: <p class="notice warning">This is a notice.</p>
 *
 * This file is safe to keep when updating Parsedown or ParsedownExtra.
 */
class ParsedownPureblog extends ParsedownExtra
{
    protected function element(array $Element)
    {
        if (
            isset($Element['name']) && $Element['name'] === 'p'
            && isset($Element['handler']['function']) && $Element['handler']['function'] === 'lineElements'
            && isset($Element['handler']['argument'])
        ) {
            $text = $Element['handler']['argument'];
            $pattern = '/[ ]*{(' . $this->regexAttribute . '+)}[ ]*$/';

            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $Element['attributes'] = $this->parseAttributeData($matches[1][0]);
                $Element['handler']['argument'] = substr($text, 0, $matches[0][1]);
            }
        }

        // Add rel="noopener noreferrer" to external markdown links
        if (
            isset($Element['name']) && $Element['name'] === 'a'
            && isset($Element['attributes']['href'])
        ) {
            $href = $Element['attributes']['href'];
            // Check if it's an external link (starts with http:// or https:// or //)
            if (preg_match('/^(https?:)?\/\//i', $href)) {
                $isExternal = true;
                if (function_exists('load_config')) {
                    $config = load_config();
                    $baseUrl = trim($config['base_url'] ?? '');
                    if ($baseUrl !== '') {
                        $host = parse_url($href, PHP_URL_HOST);
                        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
                        if ($host && $baseHost && strcasecmp($host, $baseHost) === 0) {
                            $isExternal = false;
                        }
                    }
                }

                if ($isExternal) {
                    if (!isset($Element['attributes']['rel'])) {
                        $Element['attributes']['rel'] = 'noopener noreferrer';
                    }
                }
            }
        }

        return parent::element($Element);
    }

    protected function inlineLink($Excerpt)
    {
        $Link = parent::inlineLink($Excerpt);
        if ($Link === null) {
            return null;
        }

        $remainder = substr($Excerpt['text'], $Link['extent']);

        // Matches curly brace attribute block (e.g. {target="_blank" class="custom"})
        if (preg_match('/^[ ]*{(.*?)}/', $remainder, $matches)) {
            $attributeData = $this->parseCustomAttributes($matches[1]);
            if (!empty($attributeData)) {
                if (isset($attributeData['class']) && isset($Link['element']['attributes']['class'])) {
                    $attributeData['class'] = $Link['element']['attributes']['class'] . ' ' . $attributeData['class'];
                }
                $Link['element']['attributes'] = array_merge($Link['element']['attributes'], $attributeData);
                $Link['extent'] += strlen($matches[0]);
            }
        }

        return $Link;
    }

    protected function parseCustomAttributes(string $attributeString): array
    {
        $Data = array();
        $classes = array();

        // Match .class, #id, or key="value" / key='value' / key=value
        $pattern = '/\.([-\w]+)|#([-\w]+)|([-\w]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s}]+))/';
        if (preg_match_all($pattern, $attributeString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $classes[] = $match[1];
                } elseif (!empty($match[2])) {
                    $Data['id'] = $match[2];
                } elseif (!empty($match[3])) {
                    $key = $match[3];
                    $val = '';
                    if (isset($match[4]) && $match[4] !== '') {
                        $val = $match[4];
                    } elseif (isset($match[5]) && $match[5] !== '') {
                        $val = $match[5];
                    } elseif (isset($match[6]) && $match[6] !== '') {
                        $val = $match[6];
                    }
                    $Data[$key] = $val;
                }
            }
        }

        if (!empty($classes)) {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }
}
