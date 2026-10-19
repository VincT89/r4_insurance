from pathlib import Path
import json, zipfile

root = Path(__file__).resolve().parents[4]
folder = root / 'database/migrations'
keep = set('''
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_04_20_070648_add_profile_fields_to_users_table.php
2026_04_20_071015_create_teams_table.php
2026_04_20_071046_create_team_user_table.php
2026_04_20_071424_create_clients_table.php
2026_04_20_085152_create_attachments_table.php
2026_04_20_090809_create_audit_logs_table.php
2026_04_20_092617_add_role_to_users_table.php
2026_04_20_093651_create_notifications_table.php
2026_04_20_101447_add_password_changed_at_to_users_table.php
2026_04_21_181327_add_type_to_attachments_table.php
2026_04_26_144506_create_personal_access_tokens_table.php
2026_04_26_144546_create_integration_logs_table.php
2026_05_05_100000_add_marketing_identity_fields_to_clients_table.php
2026_05_06_191124_add_normalized_phone_to_clients_table.php
2026_05_09_092426_create_user_daily_notes_table.php
2026_05_09_092446_create_user_daily_note_entries_table.php
2026_05_09_092501_create_user_daily_note_checklist_items_table.php
2026_07_16_112304_create_system_heartbeats_table.php
2026_07_16_112720_create_system_command_runs_table.php
2026_07_30_120000_create_integration_idempotency_keys_table.php
2026_09_10_100000_add_commercial_user_id_to_clients_table.php
2026_09_16_100000_add_commercial_notes_to_clients_table.php
2026_09_19_100000_add_client_material_category_to_attachments.php
2026_10_14_120000_remove_retired_modules.php
'''.split())
files = {p.name: p for p in folder.glob('*.php')}
assert keep <= files.keys(), 'Missing required migrations'
backup = Path(__file__).resolve().parent
archive = backup / 'migrazioni-prima-della-pulizia.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as z:
    for name, path in sorted(files.items()):
        z.write(path, 'database/migrations/' + name)
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
remove = ['database/migrations/' + name for name in sorted(files.keys() - keep)]
(backup / 'migration-removals.json').write_text(json.dumps(remove, indent=2), encoding='utf-8')
print(json.dumps({'before': len(files), 'remove': len(remove), 'keep': len(keep), 'backup_verified': True}))
