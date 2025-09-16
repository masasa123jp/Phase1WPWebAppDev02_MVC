-- DDL for RORO magazine system with issue and page tables

-- Drop existing tables if they exist to ensure a clean install
DROP TABLE IF EXISTS `RORO_MAGAZINE_PAGE`;
DROP TABLE IF EXISTS `RORO_MAGAZINE_ISSUE`;

-- Table to store magazine issues (e.g. 2025-06, 2025-07)
-- Each record represents one issue released in a given month.  The
-- `issue_code` should be a unique identifier such as '2025-06'.
CREATE TABLE `RORO_MAGAZINE_ISSUE` (
  `issue_id`     INT AUTO_INCREMENT PRIMARY KEY,
  `issue_code`   VARCHAR(32) NOT NULL UNIQUE,
  `title`        VARCHAR(255) NOT NULL,
  `theme_title`  VARCHAR(255) DEFAULT NULL,
  `theme_desc`   TEXT DEFAULT NULL,
  `issue_date`   DATE,
  `is_active`    TINYINT(1) DEFAULT 1,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table to store each page belonging to an issue.  There can be
-- multiple pages per issue; this design allows storing cover,
-- content, and back cover pages individually.  The `page_number`
-- column represents the order of the page within the issue.  A
-- unique index on (issue_id, page_number) prevents duplicate page
-- numbers within the same issue.
CREATE TABLE `RORO_MAGAZINE_PAGE` (
  `page_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `issue_id`     INT NOT NULL,
  `page_number`  INT NOT NULL,
  `page_type`    ENUM('cover','content','back_cover') DEFAULT 'content',
  `title`        VARCHAR(255) DEFAULT NULL,
  `summary`      TEXT DEFAULT NULL,
  `content_html` TEXT DEFAULT NULL,
  `image`        LONGBLOB DEFAULT NULL,
  `image_mime`   VARCHAR(50) DEFAULT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`issue_id`) REFERENCES `RORO_MAGAZINE_ISSUE` (`issue_id`) ON DELETE CASCADE,
  UNIQUE KEY `idx_issue_page` (`issue_id`, `page_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;