<?php

namespace Model\Issue;

/**
 * Class Tag
 *
 * @property int $id
 * @property string $tag
 * @property int $issue_id
 */
class Tag extends \Model
{
    protected $_table_name = "issue_tag";

    /**
     * Delete all stored tags for an issue
     * @param  int $issueId
     * @return Tag
     */
    public function deleteByIssueId(int $issueId): Tag
    {
        $this->db->exec("DELETE FROM {$this->_table_name} WHERE issue_id = ?", $issueId);
        return $this;
    }

    /**
     * Get a multidimensional array representing a tag cloud
     * @return array
     */
    public function cloud(): array
    {
        return $this->db->exec("SELECT tag, COUNT(*) AS freq FROM {$this->_table_name} GROUP BY tag ORDER BY freq DESC");
    }

    /**
     * Find issues with the given/current tag
     * @param  string $tag
     * @return array Issue IDs
     */
    public function issues($tag = ''): array
    {
        if (!$tag) {
            $tag = $this->get("tag");
        }
        $result = $this->db->exec("SELECT DISTINCT issue_id FROM {$this->_table_name} WHERE tag = ?", $tag);
        $return = [];
        foreach ($result as $r) {
            $return[] = $r["issue_id"];
        }
        return $return;
    }
}
