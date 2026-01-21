import os, json
p = r"D:/vs-code/study/bd2/course_project/moskva.geojson"
print('size_bytes', os.path.getsize(p))
counts = {'feature':0,'point':0,'polygon':0,'multipolygon':0}
pat = {
  'feature': '"type": "Feature"',
  'point': '"type": "Point"',
  'polygon': '"type": "Polygon"',
  'multipolygon': '"type": "MultiPolygon"',
}
with open(p,'rb') as f:
    while True:
        b = f.read(1024*1024)
        if not b:
            break
        s = b.decode('utf-8','ignore')
        for k,v in pat.items():
            counts[k] += s.count(v)
print('counts', counts)
# If file is small enough, sample first feature keys
if os.path.getsize(p) < 50*1024*1024:
    with open(p,'r',encoding='utf-8',errors='ignore') as f:
        j = json.load(f)
    feat0 = j['features'][0]
    print('geom_type0', feat0.get('geometry',{}).get('type'))
    print('properties_keys0', sorted(list(feat0.get('properties',{}).keys())))
else:
    print('skip_full_load')
