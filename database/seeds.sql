INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('app_name', 'Akıllı Zikir & Hatim', NOW()),
('site_url', 'https://zikir.next-sosyal.com', NOW()),
('default_daily_target', '1000', NOW()),
('community_enabled', '1', NOW()),
('offline_mode_enabled', '1', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

INSERT INTO zikirs (id, title, arabic_text, meaning, default_target, is_favorite, is_active, sort_order, created_at) VALUES
(1, 'Sübhânallah', 'سُبْحَانَ اللهِ', 'Allah her türlü noksandan münezzehtir.', 1000, 1, 1, 10, NOW()),
(2, 'Elhamdülillah', 'اَلْحَمْدُ لِلّٰهِ', 'Hamd, âlemlerin Rabbi olan Allah’adır.', 1000, 1, 1, 20, NOW()),
(3, 'Allahu Ekber', 'اَللهُ اَكْبَرُ', 'Allah her şeyden büyüktür.', 1000, 1, 1, 30, NOW()),
(4, 'Estağfirullah', 'اَسْتَغْفِرُ اللهَ', 'Allah’tan mağfiret dilerim.', 1000, 1, 1, 40, NOW()),
(5, 'Salavat', 'اللّٰهُمَّ صَلِّ عَلٰى مُحَمَّدٍ', 'Peygamber Efendimize salât ve selâm.', 1000, 1, 1, 50, NOW()),
(6, 'Lâ ilâhe illallah', 'لَا اِلٰهَ اِلَّا اللهُ', 'Allah’tan başka ilah yoktur.', 1000, 0, 1, 60, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), arabic_text = VALUES(arabic_text), meaning = VALUES(meaning), default_target = VALUES(default_target), is_favorite = VALUES(is_favorite), is_active = VALUES(is_active), sort_order = VALUES(sort_order);

INSERT INTO daily_contents (id, title, body, reference_text, is_active, created_at) VALUES
(1, 'Kalplerin Huzuru', 'Bilesiniz ki, kalpler ancak Allah’ın zikriyle huzur bulur.', 'Ra’d Suresi, 28. Ayet', 1, NOW()),
(2, 'Bugünün Niyeti', 'Bugün dilimizi zikirle, kalbimizi dua ile, vaktimizi hayırla güzelleştirelim.', 'Günlük Hatırlatma', 1, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body), reference_text = VALUES(reference_text), is_active = VALUES(is_active);

INSERT INTO zikir_sessions (id, title, zikir_id, subtitle, target_count, current_count, participant_count, is_live, created_at) VALUES
(1, 'Sübhânallah Zikri', 1, 'Beraber zikrediyor, bereketi paylaşıyoruz.', 25000, 18760, 1247, 1, NOW()),
(2, 'Elhamdülillah Zikri', 2, 'Şükür halkasına katıl.', 15000, 8560, 856, 1, NOW()),
(3, 'Allahu Ekber Zikri', 3, 'Tekbir halkasına katıl.', 10000, 5120, 512, 1, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), zikir_id = VALUES(zikir_id), subtitle = VALUES(subtitle), target_count = VALUES(target_count), current_count = VALUES(current_count), participant_count = VALUES(participant_count), is_live = VALUES(is_live);

INSERT INTO dua_circles (id, title, subtitle, participant_count, is_live, created_at) VALUES
(1, 'Toplu Dua Halkası', 'Beraber duâ edelim, dualarımız kabul olsun.', 2386, 1, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), subtitle = VALUES(subtitle), participant_count = VALUES(participant_count), is_live = VALUES(is_live);

INSERT INTO dua_requests (id, circle_id, nickname, title, body, amin_count, is_approved, created_at) VALUES
(1, 1, 'Ayşe K.', 'Hasta annem için şifa', 'Rabbim şifa versin, acil şifalar diliyorum.', 128, 1, NOW()),
(2, 1, 'Mehmet A.', 'Sınavda başarı', 'Yarınki sınavımda yardımınızı ve duanızı istiyorum.', 96, 1, NOW()),
(3, 1, 'Hasan Y.', 'Rızık ve bereket', 'İşlerimin hayırlı gitmesi için dua eder misiniz?', 74, 1, NOW())
ON DUPLICATE KEY UPDATE circle_id = VALUES(circle_id), nickname = VALUES(nickname), title = VALUES(title), body = VALUES(body), amin_count = VALUES(amin_count), is_approved = VALUES(is_approved);

INSERT INTO hatims (id, title, description, status, participant_count, created_at) VALUES
(1, '30 Cüz Online Hatim', 'Beraber okuyalım, beraber tamamlayalım.', 'active', 1842, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), status = VALUES(status), participant_count = VALUES(participant_count);

INSERT IGNORE INTO hatim_juz (hatim_id, juz_number, status, nickname, client_id, reserved_at, completed_at, updated_at) VALUES
(1,1,'completed','Fatma A.',NULL,NOW(),NOW(),NOW()),
(1,2,'reserved','Ali K.',NULL,NOW(),NULL,NOW()),
(1,3,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,4,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,5,'completed','Zeynep Y.',NULL,NOW(),NOW(),NOW()),
(1,6,'completed','Mustafa T.',NULL,NOW(),NOW(),NOW()),
(1,7,'reserved','Sen',NULL,NOW(),NULL,NOW()),
(1,8,'reserved','Mehmet A.',NULL,NOW(),NULL,NOW()),
(1,9,'completed','Ayşe K.',NULL,NOW(),NOW(),NOW()),
(1,10,'completed','Hasan Y.',NULL,NOW(),NOW(),NOW()),
(1,11,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,12,'reserved','Mehmet A.',NULL,NOW(),NULL,NOW()),
(1,13,'reserved','Elif S.',NULL,NOW(),NULL,NOW()),
(1,14,'completed','Meryem D.',NULL,NOW(),NOW(),NOW()),
(1,15,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,16,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,17,'reserved','Ahmet B.',NULL,NOW(),NULL,NOW()),
(1,18,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,19,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,20,'completed','Hüseyin C.',NULL,NOW(),NOW(),NOW()),
(1,21,'completed','Rabia N.',NULL,NOW(),NOW(),NOW()),
(1,22,'reserved','Ömer F.',NULL,NOW(),NULL,NOW()),
(1,23,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,24,'completed','Sümeyye P.',NULL,NOW(),NOW(),NOW()),
(1,25,'completed','İbrahim E.',NULL,NOW(),NOW(),NOW()),
(1,26,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,27,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,28,'empty',NULL,NULL,NULL,NULL,NOW()),
(1,29,'reserved','Hatice G.',NULL,NOW(),NULL,NOW()),
(1,30,'empty',NULL,NULL,NULL,NULL,NOW());

INSERT INTO app_versions (version, description, applied_at) VALUES ('1.0.0', 'İlk kurulum: mobil PWA, admin panel, offline zikir, toplu zikir, dua ve hatim sistemi.', NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
