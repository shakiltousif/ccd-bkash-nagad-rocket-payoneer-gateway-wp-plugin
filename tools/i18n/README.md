# Translation tools (not shipped)

- `bn.py` - Bangla (bn_BD) translations keyed by English source string.
- `build_po.py POT OUT_PO DIR_WITH_BN_PY` - fills the POT with `bn.py`; fails on missing strings or
  placeholder / HTML mismatches.
- `extract.py POT OUT_JSON` - lists all source strings.

Update flow (see RELEASING.md): make-pot -> add new strings to `bn.py` -> build_po.py ->
`wp i18n make-mo languages` -> `wp i18n make-json languages --no-purge`.
