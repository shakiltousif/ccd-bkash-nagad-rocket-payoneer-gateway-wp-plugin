"""Extract msgids from the POT into strings.json and print a numbered list."""
import json
import re
import sys

POT = sys.argv[1]
txt = open(POT, encoding='utf-8').read()
STR = re.compile(r'"((?:[^"\\]|\\.)*)"')


def field(block, key):
    m = re.search(r'^' + key + r' (".*"(?:\n".*")*)', block, re.M)
    if not m:
        return None
    return ''.join(json.loads('"' + s + '"') for s in STR.findall(m.group(1)))


entries = []
for block in txt.split('\n\n'):
    mid = field(block, 'msgid')
    if not mid:
        continue
    entries.append({'id': mid, 'plural': field(block, 'msgid_plural'), 'ctx': field(block, 'msgctxt')})

json.dump(entries, open(sys.argv[2], 'w', encoding='utf-8'), ensure_ascii=False, indent=0)
print(len(entries))
for i, e in enumerate(entries):
    print(i, e['id'].replace('\n', ' '))
