#!/bin/sh
# Eindeutig gekennzeichnete DEMODATEN – nur für die lokale Test-Instanz.
# Alle Einträge tragen "[DEMO]" im Namen und die Markierung _pawline_demo=1.
# Entfernen: .\scripts\pawline.ps1 demo-entfernen
set -eu
wp eval-file /project/scripts/demo-data.php
