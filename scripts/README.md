# SCRIPTS

Скрипты для работы с данными.

## Файлы

- **import_ndjson.php** - Импорт данных из NDJSON файла в MySQL
- **import_test.php** - Тестовый импорт для проверки

## Использование

```bash
php scripts/import_ndjson.php [ndjson_file] [db_name]
```

По умолчанию:
- Файл: `../moskva.ndjson`
- БД: `dtp_analysis`

