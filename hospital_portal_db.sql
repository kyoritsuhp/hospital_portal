-- ファイル名称: database_setup.sql
-- 生成日時: 2025-09-26

-- データベース作成
CREATE DATABASE IF NOT EXISTS hospital_portal DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE hospital_portal;

-- ユーザーテーブル
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 周知掲示板テーブル
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    importance ENUM('important', 'notice', 'contact') DEFAULT 'notice',
    display_start DATE DEFAULT NULL,
    display_end DATE DEFAULT NULL,
    is_visible TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 添付ファイルテーブル
CREATE TABLE notice_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notice_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notice_id) REFERENCES notices(id) ON DELETE CASCADE
);

-- カレンダー予定テーブル
CREATE TABLE calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    start_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    color VARCHAR(7) DEFAULT '#3788d8',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 初期データ挿入
INSERT INTO users (user_id, password, username) VALUES ('admin', 'admin', '管理者'),
INSERT INTO users (user_id, password, username) VALUES ('10620', '10620', '松井友弘'),

-- サンプルデータ
INSERT INTO notices (title, content, importance, created_by) VALUES 
('システムメンテナンスのお知らせ', '来週火曜日にシステムメンテナンスを実施します。', 'important', 1),
('新規職員歓迎会について', '来月の歓迎会の詳細が決まりました。', 'notice', 1),
('駐車場利用について', '駐車場の利用ルールが変更されました。', 'contact', 1);

INSERT INTO calendar_events (title, content, start_date, start_time, end_time, created_by) VALUES
('定例会議', '月次定例会議です', '2025-09-30', '09:00:00', '10:00:00', 1),
('研修会', 'セキュリティ研修', '2025-10-05', '14:00:00', '16:00:00', 1);