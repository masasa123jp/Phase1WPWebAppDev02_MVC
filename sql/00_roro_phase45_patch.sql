-- =====================================================================
-- roro_phase45_patch.sql
--
-- このスクリプトは、`roro_phase45_stage31` を WordPress 環境で動作させるための
-- 後方互換パッチです。テーマおよびプラグインが期待するテーブル名と
-- スキーマに合わせて、互換ビューの作成と不足している列の追加を行います。
--
-- 付録A：WordPress 接頭辞互換ビューの作成
-- 付録B：イベントテーブル（RORO_EVENTS_MASTER）に欠落している列を追加
--        （id 列および event_date 列）し、既存データから event_date を補完します。
--        `id` は自動採番にするため AUTO_INCREMENT 属性を付与し、MySQL 5.7 の制約
--        （AUTO_INCREMENT 列は必ずインデックスを伴う必要がある  ）
--        を満たすため UNIQUE 制約を併せて付与します。
--
-- このファイルは単独で実行可能です。データベースに対して実行する前に
-- 必ずバックアップを取得してください。

-- ---------------------------------------------------------------------
-- 付録A：接頭辞互換ビューの作成
-- WordPress の $wpdb->prefix が 'wp_' であることを前提に、
-- テーマ／プラグインが参照するテーブル名に一致するよう VIEW を作成します。

CREATE OR REPLACE VIEW `wp_RORO_CUSTOMER`           AS SELECT * FROM `RORO_CUSTOMER`;
CREATE OR REPLACE VIEW `wp_RORO_PET`                AS SELECT * FROM `RORO_PET`;
CREATE OR REPLACE VIEW `wp_RORO_MAP_FAVORITE`       AS SELECT * FROM `RORO_MAP_FAVORITE`;
CREATE OR REPLACE VIEW `wp_RORO_TRAVEL_SPOT_MASTER` AS SELECT * FROM `RORO_TRAVEL_SPOT_MASTER`;
CREATE OR REPLACE VIEW `wp_RORO_ONE_POINT_ADVICE_MASTER` AS SELECT * FROM `RORO_ONE_POINT_ADVICE_MASTER`;
CREATE OR REPLACE VIEW `wp_RORO_EVENTS_MASTER`      AS SELECT * FROM `RORO_EVENTS_MASTER`;

-- Magazine 関連テーブルの互換ビュー
CREATE OR REPLACE VIEW `wp_RORO_MAGAZINE_ISSUE` AS
  SELECT * FROM `RORO_MAGAZINE_ISSUE`;

-- MagazineModel では ARTICLE を参照しますが、実体は RORO_MAGAZINE_PAGE です。
CREATE OR REPLACE VIEW `wp_RORO_MAGAZINE_ARTICLE` AS
  SELECT
    p.page_id     AS article_id,
    p.issue_id,
    p.page_number AS sort_order,
    p.title,
    p.summary,
    p.content_html,
    p.image        AS image,
    p.image_mime   AS image_mime,
    p.is_published,
    p.published_at,
    p.slug,
    p.created_at,
    p.updated_at
  FROM `RORO_MAGAZINE_PAGE` p;

-- Analytics 用テーブルの互換ビュー
CREATE OR REPLACE VIEW `wp_RORO_MAGAZINE_VIEW`  AS SELECT * FROM `RORO_MAGAZINE_VIEW`;
CREATE OR REPLACE VIEW `wp_RORO_MAGAZINE_CLICK` AS SELECT * FROM `RORO_MAGAZINE_CLICK`;
CREATE OR REPLACE VIEW `wp_RORO_MAGAZINE_DAILY` AS SELECT * FROM `RORO_MAGAZINE_DAILY`;

-- ---------------------------------------------------------------------
-- 付録B：イベントテーブルの列追加（id, event_date）
--
-- MySQL では ALTER TABLE ... ADD COLUMN IF NOT EXISTS がサポートされていないため、
-- INFORMATION_SCHEMA を参照して列の存在を確認し、存在しない場合のみ追加する
-- 処理を PREPARE 文で実行します。ユニークキーの存在確認も同様に行います。

-- 1. id 列の追加（自動採番）
SET @has_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME  = 'RORO_EVENTS_MASTER'
    AND COLUMN_NAME = 'id'
);

--
-- MySQL では AUTO_INCREMENT 列は必ずインデックスを持つ必要があります。
-- 主キーやユニークインデックスがない場合に AUTO_INCREMENT 列を追加すると
-- エラー 1075（「自動カラムは1つしか存在できず、必ずキーとして定義されなければならない」）
-- が発生します。この要件を満たしつつ、既存の主キー（`event_id`）を壊さないために、
-- 新しく追加する `id` 列に `UNIQUE` 属性を付与します。
-- これにより `ALTER TABLE` 文の一部としてユニークインデックスが暗黙的に作成されます。
--   
SET @sql := IF(@has_id = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE FIRST;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. event_id に対するユニークキーの追加
SET @index_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'RORO_EVENTS_MASTER'
    AND INDEX_NAME   = 'uniq_event_id'
);

SET @sql := IF(@index_exists = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ADD UNIQUE KEY `uniq_event_id` (`event_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. event_date 列の追加
SET @has_event_date := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME  = 'RORO_EVENTS_MASTER'
    AND COLUMN_NAME = 'event_date'
);

SET @sql := IF(@has_event_date = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ADD COLUMN `event_date` DATE NULL AFTER `date`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. event_date を date 列から補完
UPDATE `RORO_EVENTS_MASTER`
SET `event_date` =
  CASE
    WHEN `date` REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' THEN STR_TO_DATE(`date`, '%Y-%m-%d')
    WHEN `date` REGEXP '^[0-9]{4}/[0-9]{2}/[0-9]{2}$' THEN STR_TO_DATE(`date`, '%Y/%m/%d')
    ELSE NULL
  END
WHERE `event_date` IS NULL AND `date` IS NOT NULL;
