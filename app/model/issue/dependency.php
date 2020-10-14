<?php

namespace Model\Issue;

/**
 * Class Dependency
 *
 * @property int $id
 * @property int $issue_id
 * @property int $dependency_id
 * @property string $dependency_type
 */
class Dependency extends \Model
{
    protected $_table_name = "issue_dependency";

    /**
     * Find dependency issues by issue ID
     * @param  int    $issueId
     * @param  string $orderby
     * @return array
     */
    public function findby_issue($issueId, $orderby = 'due_date'): array
    {
        return $this->db->exec(
            'SELECT d.id as d_id,i.id, i.name, i.start_date, i.due_date, i.status_closed, i.author_name, i.author_username, i.owner_name, i.owner_username, i.status_name, i.status, d.dependency_type ' .
            'FROM issue_detail i JOIN issue_dependency d on i.id = d.dependency_id ' .
            'WHERE d.issue_id = :issue_id AND i.deleted_date IS NULL ' .
            'ORDER BY :orderby',
            [':issue_id' => $issueId, ':orderby' => $orderby]
        );
    }

    /**
     * Find dependent issues by issue ID
     * @param  int    $issueId
     * @param  string $orderby
     * @return array
     */
    public function findby_dependent($issueId, $orderby = 'due_date'): array
    {
        return  $this->db->exec(
            'SELECT  d.id as d_id, i.id, i.name, i.start_date, i.due_date, i.status_closed, i.author_name, i.author_username, i.owner_name, i.owner_username, i.status_name, i.status, d.dependency_type ' .
            'FROM issue_detail i JOIN issue_dependency d on i.id = d.issue_id ' .
            'WHERE d.dependency_id = :issue_id AND i.deleted_date IS NULL ' .
            'ORDER BY :orderby',
            [':issue_id' => $issueId, ':orderby' => $orderby]
        );
    }
}
