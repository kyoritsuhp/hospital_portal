-- ---------------------------------
-- データベース: `hospital_portal`
-- ---------------------------------

-- 1. データベースの作成と選択
CREATE DATABASE IF NOT EXISTS hospital_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE hospital_portal;

-- ---------------------------------
-- 2. テーブルの作成 (外部キー制約なし)
-- ---------------------------------

-- 2-1. ユーザーテーブル (users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) UNIQUE NOT NULL COMMENT 'jinji.staff.staff_idと連携',
    password VARCHAR(255) NOT NULL COMMENT 'jinji.staff.staff_passwordと同期',
    username VARCHAR(100) NOT NULL COMMENT 'jinji.staff.nameと同期',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2-2. 掲示板テーブル (notices)
CREATE TABLE IF NOT EXISTS notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    importance ENUM('important', 'notice', 'contact') DEFAULT 'notice' COMMENT '重要度',
    display_start DATE DEFAULT NULL COMMENT '表示開始日',
    display_end DATE DEFAULT NULL COMMENT '表示終了日',
    created_by INT NOT NULL COMMENT 'users.idへの外部キー',
    hostname VARCHAR(255) DEFAULT NULL COMMENT '更新端末のホスト名',
    is_visible TINYINT(1) DEFAULT 1 COMMENT '1:表示, 0:非表示',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2-3. 添付ファイルテーブル (notice_attachments)
CREATE TABLE IF NOT EXISTS notice_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notice_id INT NOT NULL COMMENT 'notices.idへの外部キー',
    file_name VARCHAR(255) NOT NULL COMMENT '元のファイル名',
    file_path VARCHAR(512) NOT NULL COMMENT 'サーバー上のパス',
    file_size INT DEFAULT 0 COMMENT 'ファイルサイズ(bytes)',
    file_type VARCHAR(100) COMMENT 'MIMEタイプ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2-4. カレンダー予定テーブル (calendar_events)
-- (calendar.php の仕様に基づき、`category` を使用)
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    start_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    category VARCHAR(50) DEFAULT NULL COMMENT 'doctor, meeting, committee, other など',
    created_by INT NOT NULL COMMENT 'users.idへの外部キー',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------
-- 3. 外部キー制約の追加
-- (すべてのテーブル作成後に実行)
-- ---------------------------------

-- 3-1. notices (created_by) -> users (id)
-- (もし制約の追加でエラーが出る場合は、このALTER TABLE文をコメントアウトしてください)
ALTER TABLE notices
    ADD CONSTRAINT `fk_notices_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- 3-2. notice_attachments (notice_id) -> notices (id)
ALTER TABLE notice_attachments
    ADD CONSTRAINT `fk_attachments_notice_id`
    FOREIGN KEY (`notice_id`) REFERENCES `notices` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE; -- 投稿削除時に添付ファイルも自動削除

-- 3-3. calendar_events (created_by) -> users (id)
ALTER TABLE calendar_events
    ADD CONSTRAINT `fk_calendar_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE;