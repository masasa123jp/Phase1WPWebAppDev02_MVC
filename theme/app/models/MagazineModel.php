<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * MagazineModel encapsulates access to magazine issues and their articles.
 * It expects two tables: RORO_MAGAZINE_ISSUE and RORO_MAGAZINE_ARTICLE.
 */
class MagazineModel extends BaseModel {
    protected $table_issue;
    protected $table_article;
    public function __construct() {
        // BaseModel normally accepts a table name, but here we maintain two tables.
        global $wpdb;
        $this->db = $wpdb;
        $this->table_issue   = 'RORO_MAGAZINE_ISSUE';
        $this->table_article = 'RORO_MAGAZINE_ARTICLE';
    }

    /**
     * Returns all active magazine issues along with their associated articles.
     * Each issue in the returned array has an 'articles' key containing an array
     * of articles. Articles are ordered by article_id ascending.
     *
     * @return array
     */
    public function get_all() {
        $issues = $this->db->get_results(
            "SELECT * FROM `{$this->table_issue}` WHERE `is_active` = 1 ORDER BY `issue_date` DESC",
            ARRAY_A
        );
        if (empty($issues)) {
            return [];
        }
        $issue_ids = array_column($issues, 'issue_id');
        $issue_ids_esc = implode(',', array_map('intval', $issue_ids));
        $articles = $this->db->get_results(
            "SELECT * FROM `{$this->table_article}` WHERE `issue_id` IN ({$issue_ids_esc}) ORDER BY `issue_id`,`article_id`",
            ARRAY_A
        );
        $grouped = [];
        foreach ($issues as $issue) {
            $issue['articles'] = [];
            $grouped[$issue['issue_id']] = $issue;
        }
        foreach ($articles as $article) {
            $iid = $article['issue_id'];
            if (isset($grouped[$iid])) {
                $grouped[$iid]['articles'][] = $article;
            }
        }
        return array_values($grouped);
    }
}
