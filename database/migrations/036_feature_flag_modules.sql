-- Migration 036: fine-grained feature flags for guardian portal and health records.
-- Core modules stay protected; these two are optional capabilities that can be
-- switched off from Settings -> Modules Manager without hiding the rest of the
-- welfare/portal areas.

INSERT INTO installed_modules (module_key, name_en, name_ar, description_en, description_ar, category, is_enabled, is_core, installed_at, updated_at)
SELECT 'guardian_portal', 'Guardian Portal', 'بوابة ولي الأمر',
       'Family workspace for guardians to view attendance, results and notices.',
       'مساحة ولي الأمر لمتابعة الحضور والنتائج والإعلانات.',
       'portals', 1, 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM installed_modules WHERE module_key = 'guardian_portal');

INSERT INTO installed_modules (module_key, name_en, name_ar, description_en, description_ar, category, is_enabled, is_core, installed_at, updated_at)
SELECT 'health_records', 'Health Records', 'السجلات الصحية',
       'Student clinic visits and health records inside student welfare.',
       'زيارات العيادة والسجلات الصحية للطلاب ضمن رعاية الطلاب.',
       'wellbeing', 1, 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM installed_modules WHERE module_key = 'health_records');
