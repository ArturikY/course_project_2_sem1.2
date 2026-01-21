#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Конвертация GeoJSON FeatureCollection в NDJSON (Newline Delimited JSON)
Каждая строка = одна фича, удобно для потоковой обработки
"""
import json
import sys
from pathlib import Path

def convert_geojson_to_ndjson(src_path, dst_path):
    """Конвертирует FeatureCollection в NDJSON формат"""
    src = Path(src_path)
    dst = Path(dst_path)
    
    if not src.exists():
        print(f"Ошибка: файл {src} не найден")
        sys.exit(1)
    
    print(f"Чтение {src}...")
    print(f"Размер файла: {src.stat().st_size / 1024 / 1024:.2f} MB")
    
    with src.open("r", encoding="utf-8", errors="ignore") as f:
        print("Загрузка JSON...")
        data = json.load(f)
    
    if data.get("type") != "FeatureCollection":
        print("Предупреждение: ожидался FeatureCollection")
    
    features = data.get("features", [])
    total = len(features)
    print(f"Найдено фич: {total}")
    
    print(f"Запись в {dst}...")
    written = 0
    with dst.open("w", encoding="utf-8") as out:
        for i, feat in enumerate(features):
            out.write(json.dumps(feat, ensure_ascii=False))
            out.write("\n")
            written += 1
            if (i + 1) % 10000 == 0:
                print(f"  Обработано: {i + 1}/{total}")
    
    print(f"Готово! Записано {written} строк")
    print(f"Размер NDJSON файла: {dst.stat().st_size / 1024 / 1024:.2f} MB")
    return written

if __name__ == "__main__":
    src_file = "moskva.geojson"
    dst_file = "moskva.ndjson"
    
    if len(sys.argv) > 1:
        src_file = sys.argv[1]
    if len(sys.argv) > 2:
        dst_file = sys.argv[2]
    
    convert_geojson_to_ndjson(src_file, dst_file)

