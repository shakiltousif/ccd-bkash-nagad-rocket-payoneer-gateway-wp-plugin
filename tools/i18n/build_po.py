"""Fill the POT with bn.T and write the bn_BD .po; fail on missing strings or placeholder mismatch."""
import json
import re
import sys

sys.path.insert(0, sys.argv[3])
from bn import T  # noqa: E402

POT, OUT = sys.argv[1], sys.argv[2]
STR = re.compile(r'"((?:[^"\\]|\\.)*)"')
PH = re.compile(r'%(?:\d+\$)?[sd]|\{amount\}|<[^>]+>')


def field(block, key):
    m = re.search(r'^' + key + r' (".*"(?:\n".*")*)', block, re.M)
    if not m:
        return None
    return ''.join(json.loads('"' + s + '"') for s in STR.findall(m.group(1)))


def q(s):
    return json.dumps(s, ensure_ascii=False)


def check(src, dst):
    a, b = sorted(PH.findall(src)), sorted(PH.findall(dst))
    if a != b:
        raise SystemExit('Placeholder mismatch:\n  %s\n  %s\n  %s != %s' % (src, dst, a, b))


txt = open(POT, encoding='utf-8').read()
blocks = txt.split('\n\n')
out, missing, used = [], [], set()

header = (
    '# Bangla (Bangladesh) translation for CodeCareBD - Payment Gateway for WooCommerce.\n'
    '# This file is distributed under the GPLv2 or later.\n'
    'msgid ""\nmsgstr ""\n'
    '"Project-Id-Version: CodeCareBD - Payment Gateway for WooCommerce\\n"\n'
    '"Language: bn_BD\\n"\n'
    '"MIME-Version: 1.0\\n"\n'
    '"Content-Type: text/plain; charset=UTF-8\\n"\n'
    '"Content-Transfer-Encoding: 8bit\\n"\n'
    '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"\n'
    '"X-Domain: codecarebd-bkash-nagad-rocket-payoneer-gateway\\n"\n'
)
out.append(header)

for block in blocks:
    mid = field(block, 'msgid')
    if not mid:
        continue
    plural = field(block, 'msgid_plural')
    comments = [l for l in block.split('\n') if l.startswith('#')]
    ctx = field(block, 'msgctxt')
    if mid not in T:
        missing.append(mid)
        continue
    used.add(mid)
    tr = T[mid]
    lines = comments[:]
    if ctx is not None:
        lines.append('msgctxt ' + q(ctx))
    lines.append('msgid ' + q(mid))
    if plural is not None:
        forms = tr if isinstance(tr, list) else [tr, tr]
        check(mid, forms[0])
        check(plural, forms[1])
        lines.append('msgid_plural ' + q(plural))
        lines.append('msgstr[0] ' + q(forms[0]))
        lines.append('msgstr[1] ' + q(forms[1]))
    else:
        check(mid, tr)
        lines.append('msgstr ' + q(tr))
    out.append('\n'.join(lines) + '\n')

if missing:
    raise SystemExit('Missing translations (%d):\n' % len(missing) + '\n'.join(missing))

unused = set(T) - used
open(OUT, 'w', encoding='utf-8', newline='\n').write('\n'.join(out))
print('translated %d strings; unused keys: %d' % (len(used), len(unused)))
for u in sorted(unused):
    print('  unused:', u)
