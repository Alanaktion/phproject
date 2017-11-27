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
     * @param  array      $line
     * @param  array|null $block
     * @return array
     */
    protected function blockSimpleTable($line, array $block = null)
    {
        if (!isset($block) || isset($block['type']) || isset($block['interrupted'])) {
            return;
        }

        if (strpos($line['text'], '|') !== false && preg_match("/\\|([^\\|]+\\|)+/", $line['text'])) {

            // Initialize block
            if ($block['element']['name'] != 'table') {
                var_dump($block + ['DEBUG' => 1]);
                $block = array(
                    'element' => array(
                        'type' => 'SimpleTable',
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
                    'continuable' => true,
                );
            }

            // Parse table cells
            $row = $line['text'];
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
            $block['element']['text'][0]['text'][] = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $elements,
            );

            var_dump($block + ['DEBUG' => 2]);
            return $block;
        }
    }


    /**
     * Parse remaining rows of a simple table
     *
     * @param  array      $line
     * @param  array|null $block
     * @return array
     */
    protected function blockSimpleTableContinue($line, array $block)
    {
        if (isset($block['complete'])) {
            return;
        }

        // A blank newline has occurred.
        if (isset($block['interrupted'])) {
            $block['complete'] = true;
            return $block;
            // $block['element']['text'] .= "\n";
            // unset($block['interrupted']);
        }

        if (strpos($line['text'], '|') !== false && preg_match("/\\|([^\\|]+\\|)+/", $line['text'])) {

            // Parse table cells
            $row = $line['text'];
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
            $block['element']['text'][0]['text'][] = array(
                'name' => 'tr',
                'handler' => 'elements',
                'text' => $elements,
            );

            return $block;
        }

        $block['complete'] = true;
    }
}
