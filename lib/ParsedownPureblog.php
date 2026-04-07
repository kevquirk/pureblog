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

        return parent::element($Element);
    }
}
