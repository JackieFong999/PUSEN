#!/bin/bash
echo "=== time (container) ==="
date
echo "=== 1. columns ==="
mysql -uroot pusendev -e "SHOW COLUMNS FROM tblSEN_Doc" 2>/dev/null | grep Doc_Filename
echo "=== 2. staging dir ==="
ls -la /var/www/pusen01/storage/app/public/sen_docs/staging/ 2>/dev/null
echo "=== 3. tblSEN_Doc rows for SEN-025 ==="
mysql -uroot pusendev -e "SELECT SEN_Id,Doc_Seq,CHAR_LENGTH(Doc_Filename) AS len,Doc_Filename,created_at FROM tblSEN_Doc WHERE SEN_Id='SEN-025' ORDER BY Doc_Seq" 2>/dev/null
echo "=== 4. last 8 laravel.log ERROR lines ==="
grep -nE '\.ERROR' /var/www/pusen01/storage/logs/laravel.log 2>/dev/null | tail -8
echo "=== 5. recent create-sen requests (access log) ==="
grep -E "create-sen" /var/log/apache2/access.log 2>/dev/null | tail -12
echo "=== 6. apache error log tail ==="
tail -n 8 /var/log/apache2/error.log 2>/dev/null
