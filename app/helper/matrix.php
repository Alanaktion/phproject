<?php

namespace Helper;

class Matrix extends \Matrix
{
    /**
     * Merge n sorted arrays into a single array with the same sort order
     *
     * @param  array $arrays  Array of sorted arrays to merge
     */
    public function mergeSorted(array $arrays): array
    {
        $lengths = [];
        foreach ($arrays as $k => $v) {
            $lengths[$k] = is_countable($v) ? count($v) : 0;
        }

        $max = max($lengths);
        $result = [];
        for ($i = 0; $i < $max; $i++) {
            foreach ($lengths as $k => $l) {
                if ($l > $i) {
                    $result[] = $arrays[$k][$i];
                }
            }
        }

        return $result;
    }

    /**
     * Run array_merge on an array of arrays
     * @deprecated Use PHP array_merge instead
     */
    public function merge(array $arrays): array
    {
        return array_merge(...$arrays);
    }
}
