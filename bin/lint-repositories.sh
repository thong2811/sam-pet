#!/bin/bash
PASS=0; FAIL=0
for f in /var/www/html/module/Application/src/Repository/*.php; do
    result=$(php -l "$f" 2>&1)
    if echo "$result" | grep -q "No syntax errors"; then
        echo "OK: $(basename $f)"
        PASS=$((PASS+1))
    else
        echo "ERR: $(basename $f)"
        echo "  $result"
        FAIL=$((FAIL+1))
    fi
done
echo ""
echo "PASS: $PASS  FAIL: $FAIL"
