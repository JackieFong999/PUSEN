#!/bin/bash
echo "=== final dir (orphans?) ==="
ls -la /var/www/pusen01/storage/app/public/sen_docs/ 2>/dev/null
echo "=== last 20 lines of laravel.log (any level) ==="
tail -n 20 /var/www/pusen01/storage/logs/laravel.log 2>/dev/null
echo "=== tblSEN_Doc for SEN-029 ==="
mysql -uroot pusendev -e "SELECT SEN_Id,Doc_Seq,CHAR_LENGTH(Doc_Filename) AS len,Doc_Filename FROM tblSEN_Doc WHERE SEN_Id='SEN-029' ORDER BY Doc_Seq" 2>/dev/null
echo "=== where does apache log? ==="
grep -rE "CustomLog|ErrorLog" /etc/apache2/sites-enabled/ /etc/apache2/apache2.conf 2>/dev/null | grep -v "^#" | head -10
echo "=== access log files present ==="
ls -la /var/log/apache2/ 2>/dev/null | head -15
echo "=== recent upload/save hits across all access logs ==="
for f in /var/log/apache2/*access*.log; do echo "-- $f"; grep -E "create-sen/(upload|save)" "$f" 2>/dev/null | tail -6; done
