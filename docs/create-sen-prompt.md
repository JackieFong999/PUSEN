# Create SEN Page — Build Prompt (v4, FINAL, for future reference)

## Goal
Create a **Create SEN** web page following the same design language as the existing admin pages
(Staff/Student List): stat-card style form, dark theme, toast notifications.

## Layout
- Page header: **Create SEN**
- **[+ Create SEN Case]** button at the top (always enabled; pressing it enables the form and
  blanks any previously-entered values)
- Form below with **all fields disabled initially**; pressing [+ Create SEN Case] enables them.

## Fields

### A. Auto-generated (display-only)
- **SEN_Id**: `SEN-` + zero-padded 3-digit sequence. Next value = (latest `tblSEN.SEN_Id` numeric
  part) + 1. (Existing: SEN-001 … SEN-012 → next = **SEN-013**.)

### B. Editable inputs (enabled after [+ Create SEN Case])
| Field | Control | Source |
|---|---|---|
| Student_Id | Selection box | **Active students only** (`tblStudent` WHERE `Student_Status = 'ACTIVE'`); display `Student_Id — Student_Name_Eng`; selecting populates the display-only block below |
| Programme_Leader | Dropdown | `tblStaff` WHERE `Target_User_Id = 'PL'` AND `status = 0` |
| Department_Admin_Staff | Dropdown | `tblStaff` WHERE `Target_User_Id = 'DA'` AND `status = 0` |
| Counsellor | Dropdown | `tblStaff` WHERE `Target_User_Id = 'C'` AND `status = 0` |
| SEN_Officer | Dropdown | `tblStaff` WHERE `Target_User_Id = 'SO'` AND `status = 0` |
| Undergraduate_Studies_Support_Officer | Dropdown | `tblStaff` WHERE `Target_User_Id = 'USSO'` AND `status = 0` |
| SEN_Type | Dropdown | `tblSEN_Type` (column `SEN_Type`) |
| SEN_Detail | Multiline textarea | — |
| Special_Support_Required | Multiline textarea | — |
| Special_Examination_Arrangement | Multiline textarea | — |
| Temporary_Special_Support | Single-line text | — |

All staff dropdowns display **Staff_Id + Staff_Name** (e.g. `alex01 — Alex Wong`),
**enabled staff only** (`status = 0`).

### C. Display-only (auto-populated from the entered Student_Id, read-only)
- Student info from `tblStudent`: Student_Name_Eng, Student_Name_Chn, Faculty, Department,
  Prog_Sub_Code, Prog_Title, Fund_Type_Code, Student_Status
- **Subject_Teacher**: list box — path `tblStudent.Student_Id → tblStudent_Reg.Subject_Code →
  tblSubject.Teacher_Staff_Id`; display **Staff_Id + staff name** (join `tblStaff.Staff_Id`)
- **Academic_Advisor**: list box — `tblAdvisor_Student.Advisor_Id` WHERE `Student_No = Student_Id`;
  display **Advisor_Id + staff name** (join `tblStaff.Staff_Id`)
- **Subject**: list box — `Subject_Code` from `tblStudent_Reg` WHERE `Student_Id = Student_Id`
  (a student can have multiple subjects)

## Buttons
- **Save**: confirm dialog ("Save this SEN case?") → on confirm, INSERT into `tblSEN` (fields in
  B + auto SEN_Id; display-only fields NOT stored) → success toast → **clear all fields (incl.
  display-only) and disable all inputs, plus the Save and Cancel buttons**. [+ Create SEN Case]
  stays enabled.
- **Cancel**: confirm dialog → on confirm, **reset all inputs and display fields, and disable all
  inputs plus Save and Cancel buttons**.

## Notes / decisions (v5)
- **Student_Id is a SELECTION BOX** (corrected by Jackie 2026-08-06): lists Active students only
  (`tblStudent.Student_Status = 'ACTIVE'`), display `Student_Id — Student_Name_Eng`. No free-text entry;
  the display-only block auto-populates on selection (change event).
- **Responsible_SEN_Officer removed from the form** (was in earlier drafts) — `tblSEN` column stays
  in the table and is saved as NULL on insert.
- `Subject_Teacher` / `Academic_Advisor` are intentionally NOT stored in `tblSEN` (derive from student).
- Save sets `created_at` / `updated_at` / `updated_by` / `updated_ip` like other tables.

## DB facts (verified 2026-08-06)
- `tblSEN` (PK `SEN_Id` varchar10): Student_Id, Responsible_SEN_Officer, Programme_Leader,
  Department_Admin_Staff, Counsellor, SEN_Officer, Undergraduate_Studies_Support_Officer,
  SEN_Type, SEN_Detail, Special_Support_Required, Special_Examination_Arrangement,
  Temporary_Special_Support (varchar30), + audit cols. 12 rows.
- `tblStaff.Target_User_Id` distinct: AA, C, DA, PL, SAO, SO, ST, USSO (4–6 staff each);
  `status` 0 = Enable, 1 = Disable.
- `tblSubject`: Academic_Year, Semester, Subject_Code, Teacher_Staff_Id, Subject_Type.
- `tblStudent_Reg`: Student_Id → Subject_Code (7 rows).
- `tblAdvisor_Student`: Advisor_Id, Student_No, Advisor_Type (PROG_LEADER / PRIMARY), Start/End_Date.
- `tblSEN_Type`: single column `SEN_Type` (9 rows).
- DB connection: `pusen`.
