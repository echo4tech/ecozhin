SET NAMES utf8mb4;

INSERT IGNORE INTO roles (id, name, display_name) VALUES
 (1,'admin','بەڕێوەبەر'),(2,'farmer','جوتیار'),(3,'buyer','کڕیار / کارگە'),
 (4,'transport','گواستنەوە'),(5,'collection_center','ناوەندی کۆکردنەوە'),(6,'government_viewer','چاودێر');

INSERT IGNORE INTO units (id, code, name_ku, name_en, conversion_to_kg) VALUES
 (1,'kg','کیلۆگرام','Kilogram',1),(2,'ton','تەن','Ton',1000),(3,'bag','کیس','Bag',NULL),(4,'m3','مەتر سێجا','Cubic metre',NULL);

INSERT IGNORE INTO agricultural_products (id, name_ku, name_ar, name_en, category) VALUES
 (1,'هەنار','رمان','Pomegranate','fruit'),(2,'گوێز','جوز','Walnut','nut'),
 (3,'زەیتوون','زيتون','Olive','fruit'),(4,'ترێ','عنب','Grape','fruit'),(5,'هەنجیر','تين','Fig','fruit');

INSERT IGNORE INTO waste_types (id, agricultural_product_id, code, name_ku, name_ar, name_en, default_unit_id) VALUES
 (1,1,'pomegranate_peel','توێکڵی هەنار','قشر الرمان','Pomegranate peel',2),
 (2,1,'pomegranate_seed','ناوکی هەنار','بذور الرمان','Pomegranate seed',1),
 (3,2,'walnut_shell','توێکڵی گوێز','قشر الجوز','Walnut shell',2),
 (4,3,'olive_pomace','جفتی زەیتوون','جفت الزيتون','Olive pomace',2),
 (5,3,'olive_branches','دار و لقی زەیتوون','أغصان الزيتون','Olive branches',2),
 (6,4,'grape_pomace','پاشماوەی ترێ','تفل العنب','Grape pomace',2),
 (7,4,'vine_prunings','لقی ڕەز','عيدان الكرم','Vine prunings',2),
 (8,5,'fig_residue','پاشماوەی هەنجیر','مخلفات التين','Fig residue',1),
 (9,NULL,'straw','کا','قش','Straw',2),
 (10,NULL,'mixed_crop_residue','پاشماوەی تێکەڵ','مخلفات زراعية مختلطة','Mixed crop residue',2);

INSERT IGNORE INTO governorates (id, name_ku, name_ar, name_en) VALUES
 (1,'سلێمانی','السليمانية','Sulaymaniyah'),(2,'هەولێر','أربيل','Erbil'),(3,'دهۆک','دهوك','Duhok'),(4,'هەڵەبجە','حلبجة','Halabja');
INSERT IGNORE INTO districts (id, governorate_id, name_ku, name_ar, name_en) VALUES
 (1,4,'هەڵەبجە','حلبجة','Halabja'),(2,4,'خورماڵ','خورمال','Khurmal'),(3,1,'سلێمانی','السليمانية','Sulaymaniyah');

INSERT IGNORE INTO settings (setting_key, setting_value, value_type, group_name, is_public) VALUES
 ('platform_name','EcoZîn','string','general',1),
 ('default_currency','IQD','string','general',1),
 ('service_fee_percent','2','float','fees',0),
 ('match_distance_limit','100','int','matching',0),
 ('minimum_match_score','50','int','matching',0),
 ('voice_enabled','1','bool','ai',1),
 ('ai_matching_enabled','0','bool','ai',0),
 ('transport_base_fee','5000','float','logistics',0),
 ('transport_km_rate','1000','float','logistics',0),
 ('transport_weight_rate','10','float','logistics',0);
