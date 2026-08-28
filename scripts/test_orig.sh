#!/bin/bash
cd /tmp && rm -f cj.txt
BASE=http://localhost
T1=$(curl -s -c cj.txt $BASE/login | grep -oP 'name="_token" value="\K[^"]+' | head -1)
curl -s -c cj.txt -b cj.txt -X POST $BASE/login --data-urlencode "_token=$T1" --data-urlencode "staff_id=admin" --data-urlencode "password=Admin123" -o /dev/null -w "login: %{http_code}\n" -L
PAGE=$(curl -s -c cj.txt -b cj.txt "$BASE/admin/create-sen?sen_id=SEN-025")
T2=$(echo "$PAGE" | grep -oP "X-CSRF-TOKEN': '\K[^']+" | head -1)
STU=$(mysql -uroot -N pusendev -e "SELECT Student_Id FROM tblSEN WHERE SEN_Id='SEN-025'" 2>/dev/null)
printf 'test' > /tmp/t.pdf
curl -s -c cj.txt -b cj.txt -X POST $BASE/admin/create-sen/upload -F "_token=$T2" -F "sen_id=SEN-025" -F "file=@/tmp/t.pdf;filename=6-pu-3_structured_support_plan_for_direct_subsidy_scheme_primary_school_e.pdf" -w "\nupload: %{http_code}\n"
curl -s -c cj.txt -b cj.txt -X POST $BASE/admin/create-sen/save --data-urlencode "_token=$T2" --data-urlencode "student_id=$STU" --data-urlencode "sen_id=SEN-025" -w "\nsave: %{http_code}\n"
mysql -uroot pusendev -e "SELECT Doc_Seq,CHAR_LENGTH(Doc_Filename_Original) AS olen,Doc_Filename_Original FROM tblSEN_Doc WHERE SEN_Id='SEN-025' ORDER BY Doc_Seq" 2>/dev/null
