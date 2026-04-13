# Digital Attendance System - Testing Guide

Welcome to the comprehensive step-by-step testing guide. This document maps out exactly how to deploy your system into XAMPP and tests every workflow (Admin, Lecturer, and Student) to ensure it performs perfectly out of the box.

---

## Phase 1: Deployment & Database Initialization

### 1. File Placement
1. Ensure your **XAMPP Control Panel** is open and both **Apache** and **MySQL** are running.
2. Open your File Explorer. Copy the folder `digital-attendance-system` (or its contents) to `C:\xampp\htdocs\attendance_system\`.
3. You should now have the `index.php` file located exactly at `C:\xampp\htdocs\attendance_system\index.php`.

### 2. Database Setup
1. Open your browser and navigate to: `http://localhost/phpmyadmin/`
2. Click on the **Databases** tab at the top.
3. In the "Create database" field, type exactly `attendance_system` and click **Create**.
4. Select the newly created `attendance_system` database from the left sidebar.
5. Click the **Import** tab at the top.
6. Click **Choose File** and select the `database.sql` file you copied into the `htdocs` folder.
7. Scroll down and click **Import** (or **Go**).
   - *Verification*: You should see a green success message, and 10 tables should appear in the left sidebar including `users`, `courses`, `attendance_sessions`, etc.

---

## Phase 2: Testing Workflows (Detailed Walkthrough)

Open a new browser tab and navigate to the application: `http://localhost/attendance_system/`

### Test A: The Admin Workflow
1. **Login**
   - **Email:** `admin@system.edu`
   - **Password:** `password123`
   - *Result:* You will be redirected to the secure **Admin Dashboard**.
2. **Examine Statistics**
   - The top row should accurately reflect database records: **1** Student, **1** Lecturer, **1** Course / **1** Group, etc.
3. **CSV Parsing Test**
   - Create a text file on your desktop named `test_students.csv`.
   - Paste the following inside and save it:
     ```csv
     student_id, full_name, email, course_code, group_code, lecturer_name
     90001, John Doe, john.doe@system.edu, CS101, Group D, Dr. Eric
     90002, Jane Smith, jane@system.edu, CS102, Group A, Dr. Sarah
     ```
   - On the Admin Dashboard, use the **CSV Upload** form to upload `test_students.csv`.
   - *Result*: The system will securely hash passwords, create these new users dynamically, and log a `success` state in the "Recent Sync Logs" table.
4. Click **Logout** at the top right.

### Test B: The Lecturer Workflow
1. **Login**
   - **Email:** `eric@system.edu`
   - **Password:** `password123`
   - *Result:* You will be redirected to the **Lecturer Dashboard**.
2. **Verify Assigned Classes**
   - In "My Classes", you should see **CS101 (Group D)** assigned to you.
3. **Start a Session**
   - Click the blue **Start Session** button next to CS101.
   - *Result:* The page will refresh. A prominent **Active Session** card will appear at the top showing a giant **6-digit code** (e.g., `483921`).
   - *Record that 6-digit code!* You will need it for the next step.
4. Click **Logout**.

### Test C: The Student Workflow
*(Important Note: For this step to work correctly, your browser will ask for Location tracking permission. You MUST click "Allow".)*

1. **Login**
   - **Email:** `mihigo@auca.edu.rw`
   - **Password:** `password123`
   - *Result:* You will be redirected to the **Student Dashboard**.
2. **Mark Attendance**
   - Look at the "Select Course" dropdown. **CS101 - Group D** will be an option. Select it.
   - Enter the **6-digit code** you generated as the Lecturer.
   - Click **Verify GPS & Mark Present**.
3. **GPS Verification Behaviors:**
   - **Scenario 1 (Valid):** Because the classroom GPS checks are strict, if you are physically located near the coordinates generated in the DB, or if the dummy coordinates allow a mathematical pass, the API responds: *"Attendance marked successfully."*
   - **Scenario 2 (Too Far):** If you are naturally >50 meters away from the classroom coordinates established for CS101 in the dummy data (-1.956699, 30.063162), the server will mathematically reject the API call and explicitly tell you how many meters away you are.
   - **Scenario 3 (Location Denied):** If you deny browser permissions, the Javascript will catch this and throw a red error box.
4. **Attendance Summary**
   - To the right, your **Progress Ring** will dynamically compute your new attendance rate. Since 1 out of 1 sessions is attended, you will see a green bar outputting `100% PERFECT ATTENDANCE`.
5. Click **Logout**.

### Test D: The Lecturer Verification & Teardown
1. **Login** back in as **Dr. Eric** (`eric@system.edu` / `password123`).
2. Notice the "Active Session" card now says **Attendees: 1**.
3. **Send Report**
   - Click the yellow **Send Report** button. 
   - *Result*: The system will dispatch an automated log to the Admin detailing the final attendance numbers.
4. **Close Session**
   - Click the red **Close Session** button.
   - *Result*: The active session will vanish. The QR-Code expires permanently. If a student attempts to use the 6-digit code now, the API will refuse them.

---

## Technical Notes & Bug Hunting
To ensure there are no lingering bugs, keep the browser's Developer Tools (F12) open and switch to the **Console** tab while clicking buttons. Because of the robust Vanilla JS `fetch()` requests and strict PHP validations, no hidden or 400/500 backend errors should appear!
