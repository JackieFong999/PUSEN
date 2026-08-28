#!/bin/bash
BASE=http://localhost:8081
BODY=$(sed -n 's/^body=//p' /var/www/pusen01/storage/app/public/sen_docs/../../../../../../tmp/nope 2>/dev/null || true)
# read from the bind-mounted workspace
BODY=$(grep -oP '^body=\K.*' /var/www/pusen01/scripts/../scripts/../scripts/none 2>/dev/null || true)
# use the files via the Windows bind mount (workspace is under the user profile, not mounted) -> read from /tmp copies made by jmeter? not available. Use the values from dbg3_out (already shown) - pass via env instead
CSRF="$1"
SESS="$2"
echo "csrf=$CSRF"
echo "sess head: ${SESS:0:20}... (len ${#SESS})"
echo "--- replay 1: session cookie + urlencoded body ---"
curl -s -X POST $BASE/login \
  -H "Cookie: polyu-sen-data-bank-session=$SESS" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "_token=$CSRF" \
  --data-urlencode "staff_id=admin" \
  --data-urlencode "password=Admin123" \
  -o /dev/null -w "status: %{http_code}\n"
echo "--- replay 2: session + XSRF-TOKEN cookies ---"
XS=$(grep -i "XSRF-TOKEN" /tmp/cje.txt 2>/dev/null | head -1 | awk '{print $7}')
curl -s -X POST $BASE/login \
  -H "Cookie: XSRF-TOKEN=$XS; polyu-sen-data-bank-session=$SESS" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "_token=$CSRF" \
  --data-urlencode "staff_id=admin" \
  --data-urlencode "password=Admin123" \
  -o /dev/null -w "status: %{http_code}\n"
