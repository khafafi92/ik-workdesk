import argparse
import gzip
import hashlib
import json
from pathlib import Path
import re
import shutil


parser = argparse.ArgumentParser()
parser.add_argument('source_directory', type=Path)
parser.add_argument('--output', type=Path, default=Path('resources/data/indonesia-addresses'))
args = parser.parse_args()
sql = (args.source_directory / 'wilayah.sql').read_text(encoding='utf-8')
rows = re.findall(r"\('([0-9.]+)',\s*'((?:[^'\\]|\\.|'')*)'\)", sql)
regions = {code: name.replace("\\'", "'").replace("''", "'").replace('\\\\', '\\') for code, name in rows}
postcodes = json.loads((args.source_directory / 'postcodes.json').read_text(encoding='utf-8'))
assert len(regions) == len(rows) == 91599
assert sum(code.count('.') == 0 for code in regions) == 38
assert sum(code.count('.') == 1 for code in regions) == 514
assert sum(code.count('.') == 2 for code in regions) == 7285
assert sum(code.count('.') == 3 for code in regions) == 83762
assert {code for code in regions if code.count('.') == 3} == postcodes.keys()
assert all(not value or re.fullmatch(r'[0-9]{5}', value) for value in postcodes.values())
args.output.mkdir(parents=True, exist_ok=True)
cities = {}
provinces = {}
for code, name in regions.items():
    level = code.count('.')
    if level == 0:
        provinces[code] = {}
    elif level == 1:
        province_code = code[:2]
        cities[code] = {'name': name.removeprefix('Kota Administrasi '),
                        'province': 'DKI Jakarta' if province_code == '31' else regions[province_code]}
        provinces[province_code][code] = {}
    elif level == 2:
        provinces[code[:2]][code[:5]][code] = {'name': name, 'villages': {}}
    elif level == 3:
        provinces[code[:2]][code[:5]][code[:8]]['villages'][code] = {'name': name, 'postcode': postcodes[code] or ''}


def serialized(value):
    return json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':')).encode('utf-8')


(args.output / 'cities.json').write_bytes(serialized(cities))
for code, entries in provinces.items():
    (args.output / f'{code}.json.gz').write_bytes(gzip.compress(serialized(entries), mtime=0))
for name in ['LICENSE-wilayah', 'LICENSE-postcodes']:
    shutil.copyfile(args.source_directory / name, args.output / name)
manifest = {name: hashlib.sha256((args.source_directory / name).read_bytes()).hexdigest()
            for name in ['wilayah.sql', 'postcodes.json']}
(args.output / 'source-sha256.json').write_bytes(serialized(manifest))
print(json.dumps({'cities': len(cities), 'provinces': len(provinces), 'villages': len(postcodes),
                  'output_bytes': sum(path.stat().st_size for path in args.output.iterdir())}))
