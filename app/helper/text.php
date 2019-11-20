<?php

namespace Helper;

class Text
{
    /**
     * Parses textile table structures into HTML.
     *
     * @param  string $text The textile input
     * @return string The parsed text
     */
    public function tables($text)
    {
        $text = $text . "\n\n";
        return preg_replace_callback(
            "/^(?:table(?P<tatts>_?{$this->s}{$this->a}{$this->cls})\.".
            "(?P<summary>.*)?\n)?^(?P<rows>{$this->a}{$this->cls}\.? ?\|.*\|){$this->regex_snippets['space']}*\n\n/smU",
            array($this, "fTable"),
            $text
        );
    }

    /**
     * Constructs a HTML table from a textile table structure.
     *
     * This method is used by Parser::tables() to process
     * found table structures.
     *
     * @param  array  $matches
     * @return string HTML table
     */
    protected function fTable($matches)
    {
        $tatts = $this->parseAttribs($matches['tatts'], 'table');
        $space = $this->regex_snippets['space'];

        $cap = '';
        $colgrp = '';
        $last_rgrp = '';
        $c_row = 1;
        $sum = '';
        $rows = array();

        $summary = trim($matches['summary']);

        if ($summary !== '') {
            $sum = ' summary="'.htmlspecialchars($summary, ENT_QUOTES, 'UTF-8').'"';
        }

        foreach (preg_split("/\|{$space}*?$/m", $matches['rows'], -1, PREG_SPLIT_NO_EMPTY) as $row) {
            $row = ltrim($row);

            // Caption -- can only occur on row 1, otherwise treat '|=. foo |...'
            // as a normal center-aligned cell.
            if (($c_row <= 1) && preg_match(
                "/^\|\=(?P<capts>$this->s$this->a$this->cls)\. (?P<cap>[^\n]*)(?P<row>.*)/s",
                ltrim($row),
                $cmtch
            )) {
                $capts = $this->parseAttribs($cmtch['capts']);
                $cap = "\t<caption".$capts.">".trim($cmtch['cap'])."</caption>\n";
                $row = ltrim($cmtch['row']);
                if (!$row) {
                    continue;
                }
            }

            $c_row += 1;

            // Colgroup
            if (preg_match("/^\|:(?P<cols>$this->s$this->a$this->cls\. .*)/m", ltrim($row), $gmtch)) {
                // Is this colgroup def missing a closing pipe? If so, there
                // will be a newline in the middle of $row somewhere.
                $nl = strpos($row, "\n");
                $idx = 0;

                foreach (explode('|', str_replace('.', '', $gmtch['cols'])) as $col) {
                    $gatts = $this->parseAttribs(trim($col), 'col');
                    $colgrp .= "\t<col".(($idx==0) ? "group".$gatts.">" : $gatts." />")."\n";
                    $idx++;
                }

                $colgrp .= "\t</colgroup>\n";

                if ($nl === false) {
                    continue;
                } else {
                    // Recover from our missing pipe and process the rest of the line.
                    $row = ltrim(substr($row, $nl));
                }
            }

            // Row group
            $rgrpatts = $rgrp = '';

            if (preg_match(
                "/(:?^\|(?P<part>$this->vlgn)(?P<rgrpatts>$this->s$this->a$this->cls)\.{$space}*$\n)?^(?P<row>.*)/sm",
                ltrim($row),
                $grpmatch
            )) {
                if (isset($grpmatch['part'])) {
                    if ($grpmatch['part'] === '^') {
                        $rgrp = 'head';
                    } elseif ($grpmatch['part'] === '~') {
                        $rgrp = 'foot';
                    } elseif ($grpmatch['part'] === '-') {
                        $rgrp = 'body';
                    }
                }

                if (isset($grpmatch['part'])) {
                    $rgrpatts = $this->parseAttribs($grpmatch['rgrpatts']);
                }

                if (isset($grpmatch['row'])) {
                    $row = $grpmatch['row'];
                }
            }

            if (preg_match("/^(?P<ratts>$this->a$this->cls\. )(?P<row>.*)/m", ltrim($row), $rmtch)) {
                $ratts = $this->parseAttribs($rmtch['ratts'], 'tr');
                $row = $rmtch['row'];
            } else {
                $ratts = '';
            }

            $cells = array();
            $cellctr = 0;

            foreach (explode("|", $row) as $cell) {
                $ctyp = "d";

                if (preg_match("/^_(?=[{$this->regex_snippets['space']}[:punct:]])/", $cell)) {
                    $ctyp = "h";
                }

                if (preg_match("/^(?P<catts>_?$this->s$this->a$this->cls\. )(?P<cell>.*)/s", $cell, $cmtch)) {
                    $catts = $this->parseAttribs($cmtch['catts'], 'td');
                    $cell = $cmtch['cell'];
                } else {
                    $catts = '';
                }

                if (!$this->isLiteModeEnabled()) {
                    $a = array();

                    if (preg_match('/(?<space>'.$this->regex_snippets['space'].'*)(?P<cell>.*)/s', $cell, $a)) {
                        $cell = $this->redclothLists($a['cell']);
                        $cell = $this->textileLists($cell);
                        $cell = $a['space'] . $cell;
                    }
                }

                if ($cellctr > 0) {
                    // Ignore first 'cell': it precedes the opening pipe
                    $cells[] = $this->doTagBr("t$ctyp", "\t\t\t<t$ctyp$catts>$cell</t$ctyp>");
                }

                $cellctr++;
            }

            $grp = '';

            if ($rgrp && $last_rgrp) {
                $grp .= "\t</t".$last_rgrp.">\n";
            }

            if ($rgrp) {
                $grp .= "\t<t".$rgrp.$rgrpatts.">\n";
            }

            $last_rgrp = ($rgrp) ? $rgrp : $last_rgrp;
            $rows[] = $grp."\t\t<tr$ratts>\n" . join("\n", $cells) . ($cells ? "\n" : "") . "\t\t</tr>";
            unset($cells, $catts);
        }

        $rows = join("\n", $rows) . "\n";
        $close = '';

        if ($last_rgrp) {
            $close = "\t</t".$last_rgrp.">\n";
        }

        return "<table{$tatts}{$sum}>\n".$cap.$colgrp.$rows.$close."</table>\n\n";
    }
}
