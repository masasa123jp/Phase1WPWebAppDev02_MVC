<?php
/**
 * SpotsModel encapsulates CRUD access to the RORO_TRAVEL_SPOT_MASTER table.
 *
 * 旅行スポット（ペットフレンドリーな施設など）を管理するモデルです。
 * 基本的には BaseModel の CRUD 機能をそのまま利用します。
 */
require_once __DIR__ . '/BaseModel.php';

class SpotsModel extends BaseModel {
    public function __construct() {
        // ベーステーブル名（プレフィックスなし）。BaseModel が WP プレフィックスを付与します。
        $this->table = 'RORO_TRAVEL_SPOT_MASTER';
        parent::__construct( $this->table );
    }

    /**
     * 公開（isVisible=1）のスポットを取得します。
     *
     * @return array Row objects の配列
     */
    public function get_visible() {
        // Return only spots that are visible AND published.  The `status` column
        // defaults to 'draft' and should be set to 'published' when approved.
        return $this->get_many( [ 'isVisible' => 1, 'status' => 'published' ] );
    }
}