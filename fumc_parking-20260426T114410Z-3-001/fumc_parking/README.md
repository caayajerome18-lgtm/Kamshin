# FUMC Parking Management System — PHP Backend

## Setup

1. Import `schema.sql` into your MySQL database.
2. Edit `db.php` with your DB credentials.
3. Place all files in `/fumc_parking/` under your web root.
4. Ensure `mod_rewrite` is enabled (Apache) or configure nginx accordingly.
5. Create a writable `reports/` folder: `mkdir reports && chmod 755 reports`

---

## API Endpoints

Base URL: `http://yourserver/fumc_parking/`

All protected endpoints require:
```
Authorization: Bearer <token>
Content-Type: application/json
```

---

### 🔐 AUTH

| Method | Endpoint          | Description           |
|--------|-------------------|-----------------------|
| POST   | `/login`          | Guard / admin login   |
| POST   | `/logout`         | End session           |
| POST   | `/change-password`| Change own password   |

**POST /login** body:
```json
{ "username": "admin", "password": "Admin@1234" }
```
Response: `{ "success": true, "token": "...", "user": {...} }`

---

### 🚗 VEHICLE INTAKE DASHBOARD

| Method | Endpoint          | Description                      |
|--------|-------------------|----------------------------------|
| POST   | `/vehicle-intake` | Record vehicle entry             |
| POST   | `/cancel-entry`   | Cancel a pending entry           |
| GET    | `/vehicle-entries`| List entries (filterable)        |

**POST /vehicle-intake** body:
```json
{
  "license_plate": "ND 1234",
  "vehicle_type": "Car",
  "entry_type": "employee",
  "employee_id": 5
}
```
Response includes `ticket_number`, `time_in`, and updated dashboard totals.

**GET /vehicle-entries** query params:
- `date` (YYYY-MM-DD, default today)
- `status` (parked | exited | cancelled | all)
- `type` (employee | visitor)
- `plate` (partial match)

---

### 🚪 VEHICLE EXIT DASHBOARD

| Method | Endpoint        | Description                        |
|--------|-----------------|------------------------------------|
| GET    | `/vehicle-exit` | Look up active vehicle by plate    |
| POST   | `/vehicle-exit` | Confirm exit & open gate           |

**GET /vehicle-exit?license_plate=ND1234**
Returns employee info, time in, real-time duration.

**POST /vehicle-exit** body:
```json
{ "log_id": 42 }
```
Response includes `time_out`, `duration_hours`, `duration_minutes`.

---

### 📊 DASHBOARD

| Method | Endpoint           | Description                    |
|--------|--------------------|--------------------------------|
| GET    | `/dashboard`       | Full dashboard stats           |
| GET    | `/parking-slots`   | Slot map (optionally by zone)  |

**GET /parking-slots?zone=B** — filter by zone A/B/C/D.

---

### 👤 EMPLOYEES

| Method | Endpoint          | Description          |
|--------|-------------------|----------------------|
| GET    | `/employees`      | List employees       |
| POST   | `/employees`      | Create employee      |
| PUT    | `/employees/{id}` | Update employee      |
| DELETE | `/employees/{id}` | Deactivate employee  |
| GET    | `/employee-parking?employee_id=5` | Parking history |

**POST /employees** body:
```json
{
  "employee_id": "9876543",
  "full_name": "Dr. A. Alvarez",
  "department": "Cardiology",
  "position": "Senior Doctor"
}
```

---

### 📋 REPORTS

| Method | Endpoint          | Description             |
|--------|-------------------|-------------------------|
| GET    | `/daily-report`   | Get daily log as JSON   |
| POST   | `/export-excel`   | Export CSV report       |

**GET /daily-report?date=2026-04-17**

**POST /export-excel** body:
```json
{ "date": "2026-04-17" }
```
Returns `download_url` for the generated CSV file.

---

## File Structure

```
fumc_parking/
├── api.php            ← Main router
├── db.php             ← Database connection
├── auth.php           ← Login / session / password
├── vehicle_intake.php ← Vehicle entry logic
├── vehicle_exit.php   ← Vehicle exit logic
├── employees.php      ← Employee CRUD
├── dashboard.php      ← Dashboard stats & slot map
├── reports.php        ← Daily report & CSV export
├── schema.sql         ← Database schema & seeds
├── .htaccess          ← URL rewriting
└── reports/           ← Generated CSV files (writable)
```

## Default Credentials
- Username: `admin`
- Password: `Admin@1234`

> ⚠️ Change the default password after first login!
