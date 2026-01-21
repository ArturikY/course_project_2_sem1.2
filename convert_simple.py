import json
import sys

print("Starting conversion...", flush=True)
with open('moskva.geojson', 'r', encoding='utf-8', errors='ignore') as f:
    print("Loading JSON...", flush=True)
    data = json.load(f)
    print(f"Loaded. Features: {len(data.get('features', []))}", flush=True)
    
    with open('moskva.ndjson', 'w', encoding='utf-8') as out:
        count = 0
        for feat in data['features']:
            out.write(json.dumps(feat, ensure_ascii=False))
            out.write('\n')
            count += 1
            if count % 10000 == 0:
                print(f"Written {count}...", flush=True)
        print(f"Done! Total: {count}", flush=True)

