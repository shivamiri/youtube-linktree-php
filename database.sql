-- ============================================================================
--  YouTube Linktree PHP - Database Setup
--  In phpMyAdmin, select your database and Import this file.
-- ============================================================================

-- 1. Admin users
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Channel settings
CREATE TABLE IF NOT EXISTS channel_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_name VARCHAR(100) NOT NULL,
    channel_description TEXT,
    channel_url VARCHAR(255),
    logo_type ENUM('upload', 'url') DEFAULT 'url',
    logo_upload VARCHAR(255),
    logo_url VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. Videos
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    youtube_url VARCHAR(255) NOT NULL,
    youtube_video_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    thumbnail_type ENUM('upload', 'url', 'auto') DEFAULT 'auto',
    thumbnail_upload VARCHAR(255),
    thumbnail_url VARCHAR(255),
    display_order INT DEFAULT 0,
    status ENUM('active', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin login:
--   username: admin
--   password: admin123
-- !! Log in and change this password IMMEDIATELY (Admin > Settings > Security) !!
INSERT INTO admin (username, password)
SELECT 'admin', '$2y$12$rgV97CPsoW42j7OvBSkjJetb1XnudDBKsn829JiHV4mdjdOaLCJIm'
WHERE NOT EXISTS (SELECT id FROM admin WHERE username = 'admin');

-- Default channel settings (change them in Admin > Settings)
INSERT INTO channel_settings (channel_name, channel_description, channel_url, logo_type, logo_url)
SELECT 'My YouTube Links', 'Welcome to my official YouTube Links page. Check out my latest videos below!', 'https://youtube.com/', 'url', 'https://ui-avatars.com/api/?name=YT&background=2563eb&color=fff&size=256'
WHERE NOT EXISTS (SELECT id FROM channel_settings);
