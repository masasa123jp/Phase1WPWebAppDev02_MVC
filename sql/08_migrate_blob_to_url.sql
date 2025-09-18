-- 08_migrate_blob_to_url.sql 〔BLOBは保持・URLは補助列として運用〕
--
-- 【目的】
--   ・画像は引き続き RORO_MAGAZINE_PAGE.image（LONGBLOB）を“正”として保存する。
--   ・将来的な外部ストレージ/CDN配信やキャッシュ最適化のため、補助列 image_url を準備・更新する。
--   ・アプリ側は「image_url が非NULLならURL優先、NULLならBLOBを返す」という二段構えを想定する。
--
-- 【本スクリプトで行うこと】
--   1) image_url 列が存在しなければ VARCHAR(255) で追加（存在すれば何もしない）。
--   2) image_url が NULL または空文字の行に限り、プレースホルダURLを page_id を基に自動生成して埋める。
--      拡張子は image_mime から推定（jpg/png/webp/gif を例示）。実際の保存パスに合わせて変更可。
--   3) BLOB列（image）は削除しない（BLOB設計を保持）。
--
-- 【注意事項】
--   ・このスクリプトは「実ファイルの書き出しやアップロード」は行わない。アプリ／バッチ側で対応すること。
--   ・URLのベースパスは環境に合わせて変更すること（下記では '/wp-content/uploads/magazine_images/' を仮定）。
--   ・既に image_url が手動設定済みの行は上書きしない。
--   ・phpMyAdmin でそのまま実行可能（DELIMITER 変更は不要）。

-- ------------------------------------------------------------
-- 1) image_url 列の存在チェック → 無ければ追加（VARCHAR(255)）
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'RORO_MAGAZINE_PAGE'
    AND COLUMN_NAME  = 'image_url'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `RORO_MAGAZINE_PAGE` ADD COLUMN `image_url` VARCHAR(255) NULL COMMENT ''外部ストレージ上の画像URL（BLOBの補助）。'';',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 2) image_url を未設定（NULLまたは空）の行にだけ、プレースホルダURLを設定
--    ※拡張子は image_mime に応じて推定。必要に応じて分岐を追加してください。
UPDATE `RORO_MAGAZINE_PAGE`
SET `image_url` = CONCAT(
       '/wp-content/uploads/magazine_images/',
       `page_id`,
       CASE
         WHEN `image_mime` LIKE 'image/png%'  THEN '.png'
         WHEN `image_mime` LIKE 'image/webp%' THEN '.webp'
         WHEN `image_mime` LIKE 'image/gif%'  THEN '.gif'
         ELSE '.jpg'
       END
     )
WHERE ( `image_url` IS NULL OR `image_url` = '' )
  AND `image` IS NOT NULL;  -- BLOBを持つ行のみURLを生成（後でエクスポート対象にしやすくするため）

-- ------------------------------------------------------------
-- 〔ロールバック例：このスクリプトで付けた仮URLを一括でクリアしたい場合〕
-- UPDATE `RORO_MAGAZINE_PAGE`
--   SET `image_url` = NULL
-- WHERE `image_url` LIKE '/wp-content/uploads/magazine_images/%';

-- 〔整合性チェック例〕
-- ・URLがあるのにBLOBが欠落している件数（BLOB正の運用における要注意ケース）
-- SELECT COUNT(*) FROM `RORO_MAGAZINE_PAGE` WHERE `image_url` IS NOT NULL AND `image` IS NULL;
