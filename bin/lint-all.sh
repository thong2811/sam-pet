#!/bin/bash
PASS=0; FAIL=0
for dir in \
    /var/www/html/module/Application/src/Repository \
    /var/www/html/module/Application/src/Controller \
    /var/www/html/module/Application/src/Service \
    /var/www/html/module/Application/src/Database \
    /var/www/html/module/Application/config; do
    for f in "$dir"/*.php; do
        [ -f "$f" ] || continue
        result=$(php -l "$f" 2>&1)
        if echo "$result" | grep -q "No syntax errors"; then
            echo "OK: $(basename $(dirname $f))/$(basename $f)"
            PASS=$((PASS+1))
        else
            echo "ERR: $(dirname $f)/$(basename $f)"
            echo "  $result"
            FAIL=$((FAIL+1))
        fi
    done
done
echo ""
echo "PASS: $PASS  FAIL: $FAIL"
[ $FAIL -eq 0 ] && exit 0 || exit 1
