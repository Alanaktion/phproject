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

            // Initialize block
            if ($Block['element']['name'] != 'table') {
                $Block = array(
                    'element' => array(
                        'name' => 'table',
                        'text' => array(
                            array(
                                'name' => 'tbody',
                                'handler' => 'elements',
                                'text' => array(),
                            )
                        ),
                        'handler' => 'elements',
                    ),
                    'identified' => true,
                );
            }

            // Parse table cells
            $row = $Line['text'];
            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]+`|`)+/', $row, $matches);

            $elements = array();
            foreach ($matches[0] as $index => $cell) {
                $elements[] = array(
                    'name' => 'td',
                    'handler' => 'line',
                    'text' => trim($cell),
                );
            }

            // Add table row to block
            $Block['element']['text'][0]['text'][] = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $elements,
            );

            return $Block;
        }
    }
}
