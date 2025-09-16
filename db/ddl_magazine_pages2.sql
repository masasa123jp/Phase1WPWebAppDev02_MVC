/* 1-1) 号テーブルに公開フラグ・公開時刻等を追加 */
ALTER TABLE `RORO_MAGAZINE_ISSUE`
  ADD COLUMN `is_published`   TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN `published_at`   DATETIME NULL AFTER `is_published`,
  ADD COLUMN `unpublished_at` DATETIME NULL AFTER `published_at`,
  ADD COLUMN `locale`         VARCHAR(10) NULL AFTER `issue_date`,      -- 例: ja-JP, en-US
  ADD COLUMN `visibility`     ENUM('public','unlisted','private')
                              NOT NULL DEFAULT 'public' AFTER `is_published`,
  ADD INDEX  `idx_issue_pub` (`is_published`,`issue_date`);

/* 1-2) ページテーブルに公開フラグ・公開時刻・ページ識別スラッグを追加 */
ALTER TABLE `RORO_MAGAZINE_PAGE`
  ADD COLUMN `is_published` TINYINT(1) NOT NULL DEFAULT 1 AFTER `image_mime`,
  ADD COLUMN `published_at` DATETIME NULL AFTER `is_published`,
  ADD COLUMN `slug`         VARCHAR(128) NULL AFTER `title`,
  ADD INDEX  `idx_page_pub` (`issue_id`,`is_published`,`page_number`);

/* 2-1) ページ内リンクのメタ定義（論理ID化してクリック集計に使う） */
CREATE TABLE `RORO_MAGAZINE_LINK` (
  `link_id`     BIGINT AUTO_INCREMENT PRIMARY KEY,
  `issue_id`    INT NOT NULL,
  `page_id`     INT NOT NULL,
  `anchor_text` VARCHAR(255)  DEFAULT NULL,
  `href`        TEXT          NOT NULL,
  `is_external` TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`issue_id`) REFERENCES `RORO_MAGAZINE_ISSUE`(`issue_id`) ON DELETE CASCADE,
  FOREIGN KEY (`page_id`)  REFERENCES `RORO_MAGAZINE_PAGE`(`page_id`)  ON DELETE CASCADE,
  INDEX `idx_link_issue_page` (`issue_id`,`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* 2-2) 閲覧イベント（1ページ表示＝1行）。滞在時間も保存 */
CREATE TABLE `RORO_MAGAZINE_VIEW` (
  `view_id`     BIGINT AUTO_INCREMENT PRIMARY KEY,
  `issue_id`    INT      NOT NULL,
  `page_id`     INT      NULL,                -- 表紙のみ or 号一覧から入って未確定の時は NULL でも良い
  `wp_user_id`  BIGINT   NULL,                -- 匿名アクセスは NULL
  `session_id`  VARCHAR(64)  NULL,            -- JSで生成/付与（1st-party Cookie 等）
  `ip_hash`     CHAR(64)   NULL,              -- PII回避のためハッシュ化（SHA-256等）
  `ua_hash`     CHAR(64)   NULL,              -- UAもハッシュ（同上）
  `referer`     VARCHAR(1024) NULL,
  `device`      ENUM('pc','tablet','mobile','other') DEFAULT 'other',
  `lang`        VARCHAR(10) NULL,             -- navigator.language など
  `country`     VARCHAR(2)  NULL,             -- 推定国コード（必要なら）
  `event_ts`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `dwell_ms`    INT       NULL,               -- ページ滞在ミリ秒（unload時に送信）
  FOREIGN KEY (`issue_id`) REFERENCES `RORO_MAGAZINE_ISSUE`(`issue_id`) ON DELETE CASCADE,
  FOREIGN KEY (`page_id`)  REFERENCES `RORO_MAGAZINE_PAGE`(`page_id`)  ON DELETE SET NULL,
  INDEX `idx_view_issue_page_ts` (`issue_id`,`page_id`,`event_ts`),
  INDEX `idx_view_user_session`  (`wp_user_id`,`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* 2-3) クリックイベント（リンク押下を1行として保存） */
CREATE TABLE `RORO_MAGAZINE_CLICK` (
  `click_id`    BIGINT AUTO_INCREMENT PRIMARY KEY,
  `issue_id`    INT      NOT NULL,
  `page_id`     INT      NOT NULL,
  `link_id`     BIGINT   NULL,                -- 事前に RORO_MAGAZINE_LINK を登録できる場合
  `href`        TEXT     NOT NULL,            -- 動的生成などで link_id が取れない場合に備えて URL も保存
  `wp_user_id`  BIGINT   NULL,
  `session_id`  VARCHAR(64) NULL,
  `ip_hash`     CHAR(64)   NULL,
  `event_ts`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`issue_id`) REFERENCES `RORO_MAGAZINE_ISSUE`(`issue_id`) ON DELETE CASCADE,
  FOREIGN KEY (`page_id`)  REFERENCES `RORO_MAGAZINE_PAGE`(`page_id`)  ON DELETE CASCADE,
  FOREIGN KEY (`link_id`)  REFERENCES `RORO_MAGAZINE_LINK`(`link_id`)  ON DELETE SET NULL,
  INDEX `idx_click_issue_page_ts` (`issue_id`,`page_id`,`event_ts`),
  INDEX `idx_click_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* 2-4) 日次集計スナップショット（任意。定期バッチで更新） */
/* ←ここが修正点：ON DELETE CASCADE に変更し、NOT NULL のまま矛盾を解消 */
CREATE TABLE `RORO_MAGAZINE_DAILY` (
  `date`         DATE NOT NULL,
  `issue_id`     INT  NOT NULL,
  `page_id`      INT  NOT NULL,
  `views`        INT  NOT NULL DEFAULT 0,
  `unique_uv`    INT  NOT NULL DEFAULT 0,
  `clicks`       INT  NOT NULL DEFAULT 0,
  `avg_dwell_ms` INT  NOT NULL DEFAULT 0,
  PRIMARY KEY (`date`,`issue_id`,`page_id`),
  FOREIGN KEY (`issue_id`) REFERENCES `RORO_MAGAZINE_ISSUE`(`issue_id`) ON DELETE CASCADE,
  FOREIGN KEY (`page_id`)  REFERENCES `RORO_MAGAZINE_PAGE`(`page_id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* 2-5) 号×ユーザーの累積指標（任意。レコメンドやMA向け） */
CREATE TABLE `RORO_MAGAZINE_AUDIENCE` (
  `id`         BIGINT AUTO_INCREMENT PRIMARY KEY,
  `issue_id`   INT     NOT NULL,
  `wp_user_id` BIGINT  NOT NULL,
  `first_view_at` DATETIME NULL,
  `last_view_at`  DATETIME NULL,
  `total_views`   INT  NOT NULL DEFAULT 0,
  `total_clicks`  INT  NOT NULL DEFAULT 0,
  `total_dwell_ms` INT NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_issue_user` (`issue_id`,`wp_user_id`),
  FOREIGN KEY (`issue_id`) REFERENCES `RORO_MAGAZINE_ISSUE`(`issue_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
