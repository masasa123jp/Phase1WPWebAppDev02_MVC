/* ===================================================================
   Project RORO - Multilingual Columns Patch (based on 2025-08-22_2)
   Target base: DDL_20250830.sql (already applied)
   Safe to run multiple times (uses IF NOT EXISTS)
   Charset/Collation follow existing tables (utf8mb4_unicode_520_ci)
   =================================================================== */

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/* 1) カテゴリ（カテゴリ名の多言語） */
ALTER TABLE RORO_CATEGORY_MASTER
  ADD COLUMN category_name_en VARCHAR(255) NULL COMMENT '表示名（英語）',
  ADD COLUMN category_name_zh VARCHAR(255) NULL COMMENT '表示名（中国語）',
  ADD COLUMN category_name_ko VARCHAR(255) NULL COMMENT '表示名（韓国語）';

/* 2) 犬種マスタ（カテゴリ説明の多言語） */
ALTER TABLE RORO_BREED_MASTER
  ADD COLUMN category_description_en VARCHAR(255) NULL COMMENT 'カテゴリ説明（英語）',
  ADD COLUMN category_description_zh VARCHAR(255) NULL COMMENT 'カテゴリ説明（中国語）',
  ADD COLUMN category_description_ko VARCHAR(255) NULL COMMENT 'カテゴリ説明（韓国語）';

/* 3) ペット（表示ラベルの多言語） */
ALTER TABLE RORO_PET
  ADD COLUMN breed_label_en VARCHAR(255) NULL COMMENT '表示ラベル（英語）' AFTER breed_label,
  ADD COLUMN breed_label_zh VARCHAR(255) NULL COMMENT '表示ラベル（中国語）' AFTER breed_label_en,
  ADD COLUMN breed_label_ko VARCHAR(255) NULL COMMENT '表示ラベル（韓国語）' AFTER breed_label_zh;

/* 4) 写真（キャプションの多言語） */
ALTER TABLE RORO_PHOTO
  ADD COLUMN caption_en TEXT NULL COMMENT 'キャプション（英語）' AFTER caption,
  ADD COLUMN caption_zh TEXT NULL COMMENT 'キャプション（中国語）' AFTER caption_en,
  ADD COLUMN caption_ko TEXT NULL COMMENT 'キャプション（韓国語）' AFTER caption_zh;

/* 5) Google Maps マスタ（施設情報の多言語） */
ALTER TABLE RORO_GOOGLE_MAPS_MASTER
  ADD COLUMN name_en VARCHAR(255) NULL COMMENT '施設名（英語）' AFTER name,
  ADD COLUMN name_zh VARCHAR(255) NULL COMMENT '施設名（中国語）' AFTER name_en,
  ADD COLUMN name_ko VARCHAR(255) NULL COMMENT '施設名（韓国語）' AFTER name_zh,
  ADD COLUMN prefecture_en VARCHAR(64) NULL COMMENT '都道府県（英語）' AFTER prefecture,
  ADD COLUMN prefecture_zh VARCHAR(64) NULL COMMENT '都道府県（中国語）' AFTER prefecture_en,
  ADD COLUMN prefecture_ko VARCHAR(64) NULL COMMENT '都道府県（韓国語）' AFTER prefecture_zh,
  ADD COLUMN region_en VARCHAR(64) NULL COMMENT '地域（英語）' AFTER region,
  ADD COLUMN region_zh VARCHAR(64) NULL COMMENT '地域（中国語）' AFTER region_en,
  ADD COLUMN region_ko VARCHAR(64) NULL COMMENT '地域（韓国語）' AFTER region_zh,
  ADD COLUMN genre_en VARCHAR(64) NULL COMMENT 'ジャンル（英語）' AFTER genre,
  ADD COLUMN genre_zh VARCHAR(64) NULL COMMENT 'ジャンル（中国語）' AFTER genre_en,
  ADD COLUMN genre_ko VARCHAR(64) NULL COMMENT 'ジャンル（韓国語）' AFTER genre_zh,
  ADD COLUMN address_en VARCHAR(255) NULL COMMENT '住所（英語）' AFTER address,
  ADD COLUMN address_zh VARCHAR(255) NULL COMMENT '住所（中国語）' AFTER address_en,
  ADD COLUMN address_ko VARCHAR(255) NULL COMMENT '住所（韓国語）' AFTER address_zh,
  ADD COLUMN opening_time_en VARCHAR(64) NULL COMMENT '開店時間（英語）' AFTER opening_time,
  ADD COLUMN opening_time_zh VARCHAR(64) NULL COMMENT '開店時間（中国語）' AFTER opening_time_en,
  ADD COLUMN opening_time_ko VARCHAR(64) NULL COMMENT '開店時間（韓国語）' AFTER opening_time_zh,
  ADD COLUMN closing_time_en VARCHAR(64) NULL COMMENT '閉店時間（英語）' AFTER closing_time,
  ADD COLUMN closing_time_zh VARCHAR(64) NULL COMMENT '閉店時間（中国語）' AFTER closing_time_en,
  ADD COLUMN closing_time_ko VARCHAR(64) NULL COMMENT '閉店時間（韓国語）' AFTER closing_time_zh,
  ADD COLUMN review_en TEXT NULL COMMENT 'レビュー（英語）' AFTER review,
  ADD COLUMN review_zh TEXT NULL COMMENT 'レビュー（中国語）' AFTER review_en,
  ADD COLUMN review_ko TEXT NULL COMMENT 'レビュー（韓国語）' AFTER review_zh,
  ADD COLUMN description_en TEXT NULL COMMENT '説明（英語）' AFTER description,
  ADD COLUMN description_zh TEXT NULL COMMENT '説明（中国語）' AFTER description_en,
  ADD COLUMN description_ko TEXT NULL COMMENT '説明（韓国語）' AFTER description_zh;

/* 6) 旅行スポットマスタ（多言語） */
ALTER TABLE RORO_TRAVEL_SPOT_MASTER
  ADD COLUMN prefecture_en VARCHAR(64) NULL COMMENT '都道府県（英語）' AFTER prefecture,
  ADD COLUMN prefecture_zh VARCHAR(64) NULL COMMENT '都道府県（中国語）' AFTER prefecture_en,
  ADD COLUMN prefecture_ko VARCHAR(64) NULL COMMENT '都道府県（韓国語）' AFTER prefecture_zh,
  ADD COLUMN region_en VARCHAR(64) NULL COMMENT '地域（英語）' AFTER region,
  ADD COLUMN region_zh VARCHAR(64) NULL COMMENT '地域（中国語）' AFTER region_en,
  ADD COLUMN region_ko VARCHAR(64) NULL COMMENT '地域（韓国語）' AFTER region_zh,
  ADD COLUMN spot_area_en VARCHAR(128) NULL COMMENT 'スポットエリア（英語）' AFTER spot_area,
  ADD COLUMN spot_area_zh VARCHAR(128) NULL COMMENT 'スポットエリア（中国語）' AFTER spot_area_en,
  ADD COLUMN spot_area_ko VARCHAR(128) NULL COMMENT 'スポットエリア（韓国語）' AFTER spot_area_zh,
  ADD COLUMN genre_en VARCHAR(64) NULL COMMENT 'ジャンル（英語）' AFTER genre,
  ADD COLUMN genre_zh VARCHAR(64) NULL COMMENT 'ジャンル（中国語）' AFTER genre_en,
  ADD COLUMN genre_ko VARCHAR(64) NULL COMMENT 'ジャンル（韓国語）' AFTER genre_zh,
  ADD COLUMN name_en VARCHAR(255) NULL COMMENT '施設名（英語）' AFTER name,
  ADD COLUMN name_zh VARCHAR(255) NULL COMMENT '施設名（中国語）' AFTER name_en,
  ADD COLUMN name_ko VARCHAR(255) NULL COMMENT '施設名（韓国語）' AFTER name_zh,
  ADD COLUMN address_en VARCHAR(255) NULL COMMENT '住所（英語）' AFTER address,
  ADD COLUMN address_zh VARCHAR(255) NULL COMMENT '住所（中国語）' AFTER address_en,
  ADD COLUMN address_ko VARCHAR(255) NULL COMMENT '住所（韓国語）' AFTER address_zh,
  ADD COLUMN opening_time_en VARCHAR(64) NULL COMMENT '開店時間（英語）' AFTER opening_time,
  ADD COLUMN opening_time_zh VARCHAR(64) NULL COMMENT '開店時間（中国語）' AFTER opening_time_en,
  ADD COLUMN opening_time_ko VARCHAR(64) NULL COMMENT '開店時間（韓国語）' AFTER opening_time_zh,
  ADD COLUMN closing_time_en VARCHAR(64) NULL COMMENT '閉店時間（英語）' AFTER closing_time,
  ADD COLUMN closing_time_zh VARCHAR(64) NULL COMMENT '閉店時間（中国語）' AFTER closing_time_en,
  ADD COLUMN closing_time_ko VARCHAR(64) NULL COMMENT '閉店時間（韓国語）' AFTER closing_time_zh,
  ADD COLUMN review_en TEXT NULL COMMENT 'レビュー（英語）' AFTER review,
  ADD COLUMN review_zh TEXT NULL COMMENT 'レビュー（中国語）' AFTER review_en,
  ADD COLUMN review_ko TEXT NULL COMMENT 'レビュー（韓国語）' AFTER review_zh;

/* 7) MAP お気に入り（ラベルの多言語） */
ALTER TABLE RORO_MAP_FAVORITE
  ADD COLUMN label_en VARCHAR(255) NULL COMMENT 'ラベル（英語）' AFTER label,
  ADD COLUMN label_zh VARCHAR(255) NULL COMMENT 'ラベル（中国語）' AFTER label_en,
  ADD COLUMN label_ko VARCHAR(255) NULL COMMENT 'ラベル（韓国語）' AFTER label_zh;

/* 8) OPAM（タイトル/本文/対象ペットの多言語） */
ALTER TABLE RORO_ONE_POINT_ADVICE_MASTER
  ADD COLUMN title_en VARCHAR(255) NULL COMMENT 'タイトル（英語）' AFTER title,
  ADD COLUMN title_zh VARCHAR(255) NULL COMMENT 'タイトル（中国語）' AFTER title_en,
  ADD COLUMN title_ko VARCHAR(255) NULL COMMENT 'タイトル（韓国語）' AFTER title_zh,
  ADD COLUMN body_en MEDIUMTEXT NULL COMMENT '本文（英語）' AFTER body,
  ADD COLUMN body_zh MEDIUMTEXT NULL COMMENT '本文（中国語）' AFTER body_en,
  ADD COLUMN body_ko MEDIUMTEXT NULL COMMENT '本文（韓国語）' AFTER body_zh,
  ADD COLUMN for_which_pets_en VARCHAR(255) NULL COMMENT '対象ペット（英語）' AFTER for_which_pets,
  ADD COLUMN for_which_pets_zh VARCHAR(255) NULL COMMENT '対象ペット（中国語）' AFTER for_which_pets_en,
  ADD COLUMN for_which_pets_ko VARCHAR(255) NULL COMMENT '対象ペット（韓国語）' AFTER for_which_pets_zh;

/* 9) イベント（名称/場所/会場/住所/都道府県/市区町村の多言語） */
ALTER TABLE RORO_EVENTS_MASTER
  ADD COLUMN name_en VARCHAR(255) NULL COMMENT 'イベント名（英語）' AFTER name,
  ADD COLUMN name_zh VARCHAR(255) NULL COMMENT 'イベント名（中国語）' AFTER name_en,
  ADD COLUMN name_ko VARCHAR(255) NULL COMMENT 'イベント名（韓国語）' AFTER name_zh,
  ADD COLUMN location_en VARCHAR(255) NULL COMMENT '場所（英語）' AFTER location,
  ADD COLUMN location_zh VARCHAR(255) NULL COMMENT '場所（中国語）' AFTER location_en,
  ADD COLUMN location_ko VARCHAR(255) NULL COMMENT '場所（韓国語）' AFTER location_zh,
  ADD COLUMN venue_en VARCHAR(255) NULL COMMENT '会場（英語）' AFTER venue,
  ADD COLUMN venue_zh VARCHAR(255) NULL COMMENT '会場（中国語）' AFTER venue_en,
  ADD COLUMN venue_ko VARCHAR(255) NULL COMMENT '会場（韓国語）' AFTER venue_zh,
  ADD COLUMN address_en VARCHAR(255) NULL COMMENT '住所（英語）' AFTER address,
  ADD COLUMN address_zh VARCHAR(255) NULL COMMENT '住所（中国語）' AFTER address_en,
  ADD COLUMN address_ko VARCHAR(255) NULL COMMENT '住所（韓国語）' AFTER address_zh,
  ADD COLUMN prefecture_en VARCHAR(50) NULL COMMENT '都道府県（英語）' AFTER prefecture,
  ADD COLUMN prefecture_zh VARCHAR(50) NULL COMMENT '都道府県（中国語）' AFTER prefecture_en,
  ADD COLUMN prefecture_ko VARCHAR(50) NULL COMMENT '都道府県（韓国語）' AFTER prefecture_zh,
  ADD COLUMN city_en VARCHAR(50) NULL COMMENT '市区町村（英語）' AFTER city,
  ADD COLUMN city_zh VARCHAR(50) NULL COMMENT '市区町村（中国語）' AFTER city_en,
  ADD COLUMN city_ko VARCHAR(50) NULL COMMENT '市区町村（韓国語）' AFTER city_zh;


SET FOREIGN_KEY_CHECKS = 1;

/* 実行後チェック推奨:
   SHOW CREATE TABLE RORO_TRAVEL_SPOT_MASTER\G
   SHOW COLUMNS FROM RORO_ONE_POINT_ADVICE_MASTER LIKE 'title_%';
*/
