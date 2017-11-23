<?php

namespace Lib;

/**
 * Markdown class, extending Parsedown library
 *
 * This currently adds additional format support to the table parsing
 */
class Markdown extends \Parsedown
{
    function __construct()
    {
        $this->BlockTypes['|'][] = 'SimpleTable';
    }

    /**
     * Parse a simple table block without headers
     *
     * @param  array      $Line
     * @param  array|null $Block
     * @return array
     */
    protected function blockSimpleTable($Line, array $Block = null)
    {
        if (!isset($Block) || isset($Block['type']) || isset($Block['interrupted'])) {
            return;
        }

        if (strpos($Line['text'], '|') !== false && preg_match("/\\|([^\\|]+\\|)+/", $Line['text'])) {
            $elements = array();

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

            foreach ($matches[0] as $index => $cell) {
                $elements[] = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => trim($cell),
                );
            }

            $Element = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $elements,
            );

            $Block['identified'] = true;

            if (is_string($Block['element']['text'])) {
                $Block['element']['handler'] = 'elements';
                $Block['element']['text'] = array(
                    array(
                        'name' => 'tbody',
                        'handler' => 'elements',
                        'text' => array(),
                    )
                );
            }

            $Block['element']['text'][0]['text'][] = $Element;

            return $Block;
        }
    }
}
