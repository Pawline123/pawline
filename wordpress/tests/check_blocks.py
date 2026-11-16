#!/usr/bin/env python3
"""Statische Prüfung der Block-Markup-Dateien des Themes (ohne WordPress).

Prüft: gültiges JSON in Block-Kommentaren, korrekt verschachtelte Öffnen/Schließen-
Kommentare, existierende Template-Parts und Muster-Slugs, Pattern-Header.
Prüft NICHT: ob der Block-Editor das Markup als "gültig" akzeptiert (nur in WordPress möglich).

Aufruf: python3 tests/check_blocks.py
"""
import json
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent / "wp-content/themes/pawline"
TOKEN = re.compile(r"<!--\s+(/)?wp:([a-z0-9-]+(?:/[a-z0-9-]+)?)(\s+(\{.*?\}))?\s+(/)?-->", re.S)

errors = []
pattern_slugs = set()
for p in (ROOT / "patterns").glob("*.php"):
    head = p.read_text(encoding="utf-8")[:800]
    m = re.search(r"Slug:\s*(\S+)", head)
    if not m or "Title:" not in head:
        errors.append(f"{p.name}: Pattern-Header (Title/Slug) fehlt")
    else:
        pattern_slugs.add(m.group(1))
parts = {p.stem for p in (ROOT / "parts").glob("*.html")}

files = list((ROOT / "templates").glob("*.html")) + list((ROOT / "parts").glob("*.html")) + list((ROOT / "patterns").glob("*.php"))
blocks_seen = 0
for f in files:
    text = f.read_text(encoding="utf-8")
    text = re.sub(r"<\?php.*?\?>", "PHP", text, flags=re.S)
    stack = []
    for m in TOKEN.finditer(text):
        closing, name, _, attrs, selfclose = m.groups()
        blocks_seen += 1
        if attrs:
            try:
                data = json.loads(attrs)
            except json.JSONDecodeError as e:
                errors.append(f"{f.name}: ungültiges JSON in wp:{name}: {e}")
                continue
            if name == "template-part" and data.get("slug") not in parts:
                errors.append(f"{f.name}: Template-Part '{data.get('slug')}' fehlt")
            if name == "pattern" and data.get("slug") not in pattern_slugs:
                errors.append(f"{f.name}: Muster '{data.get('slug')}' fehlt")
        if selfclose:
            continue
        if closing:
            if not stack or stack[-1] != name:
                errors.append(f"{f.name}: Schließendes wp:{name} passt nicht zu {stack[-1] if stack else 'nichts'}")
            else:
                stack.pop()
        else:
            stack.append(name)
    if stack:
        errors.append(f"{f.name}: nicht geschlossene Blöcke: {stack}")

required = ["index", "front-page", "page", "single-product", "archive-product", "taxonomy-product_cat", "page-cart", "page-checkout", "404", "search", "product-search-results"]
for r in required:
    if not (ROOT / "templates" / f"{r}.html").exists():
        errors.append(f"Template {r}.html fehlt")

theme = json.loads((ROOT / "theme.json").read_text(encoding="utf-8"))
for ct in theme.get("customTemplates", []):
    if not (ROOT / "templates" / f"{ct['name']}.html").exists():
        errors.append(f"customTemplate {ct['name']} ohne Datei")

print(f"Dateien: {len(files)}, Block-Kommentare: {blocks_seen}, Muster: {len(pattern_slugs)}, Parts: {sorted(parts)}")
if errors:
    print("FEHLER:")
    for e in errors:
        print("  -", e)
    sys.exit(1)
print("Alle statischen Block-Prüfungen bestanden.")
