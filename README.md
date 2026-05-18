# Expedia PH — Complete Setup Guide (Fresh Start)
**Version: FINAL | PHP + MySQL + REST API | XAMPP**

---

## STEP 1 — Delete the old project

Open File Explorer and **permanently delete** this folder:
```
C:\xampp\htdocs\expedia\
```
Also delete any other versions like `expedia2`, `stayhaven`, etc. from htdocs.

---

## STEP 2 — Copy the new project

Extract the zip you downloaded. Inside you will find a folder called `final`.
Rename it to `expedia` and copy it into:
```
C:\xampp\htdocs\expedia\
```
Your final structure must look exactly like this:
```
C:\xampp\htdocs\expedia\
├── index.php
├── includes\
│   ├── config.php
│   ├── header.php
│   └── footer.php
├── pages\
│   ├── login.php
│   ├── register.php
│   ├── logout.php
│   ├── search.php
│   ├── hotel.php
│   ├── booking.php
│   └── my-bookings.php
├── admin\
│   ├── _sidebar.php
│   ├── dashboard.php
│   ├── hotels.php
│   ├── rooms.php
│   ├── bookings.php
│   ├── payments.php
│   └── users.php
├── api\
│   ├── index.php
│   └── .htaccess
├── assets\
│   ├── css\main.css
│   ├── js\main.js
│   └── images\
│       ├── hotels\   ← put hotel_1.jpg to hotel_24.jpg here
│       ├── rooms\    ← put room images here
│       └── dest\     ← put dest_1.jpg to dest_9.jpg here
└── sql\
    └── expedia_ph_FINAL.sql
```

---

## STEP 3 — Drop the old database

1. Open `http://localhost/phpmyadmin`
2. In the left sidebar, click `expedia_ph` (if it exists)
3. Click the **Operations** tab at the top
4. Scroll down to "Drop the database" → click **Drop** → confirm

If it doesn't exist yet, skip this step.

---

## STEP 4 — Import the fresh database

1. In phpMyAdmin, click **New** in the left sidebar
2. Type `expedia_ph` in the database name field
3. Select `utf8mb4_unicode_ci` as the collation
4. Click **Create**
5. Click on the new `expedia_ph` database in the sidebar
6. Click the **Import** tab at the top
7. Click **Choose File** → navigate to your project and select:
   ```
   C:\xampp\htdocs\expedia\sql\expedia_ph_FINAL.sql
   ```
8. Click **Go** at the bottom

You should see a green success message. The database now has all tables and 24 hotels.

---

## STEP 5 — Open the site

Visit: `http://localhost/expedia/`

**Admin login:**
```
Email:    admin@expedia.ph
Password: Admin@1234
```

No setup.php needed. The correct password hash is already in the SQL file.

---

## STEP 6 — Add hotel photos (optional but recommended)

The site works without photos — it shows placeholder images automatically.
When you're ready to add real photos:

**Hotel images** → `assets/images/hotels/`
Name them: `hotel_1.jpg`, `hotel_2.jpg`, … `hotel_24.jpg`
Recommended size: **800 × 520 px**

**Room images** → `assets/images/rooms/`
Name them: `room_1_1.jpg` (hotel 1, room 1), `room_2_1.jpg` (hotel 2, room 1), etc.
Recommended size: **400 × 260 px**

**Destination images** → `assets/images/dest/`
Name them: `dest_1.jpg` (Makati), `dest_2.jpg` (Cebu City), `dest_3.jpg` (Boracay), etc.
Recommended size: **400 × 220 px**

To change any image path, go to **Admin → Hotels → Edit** and update the Thumbnail field.

---

## REST API Documentation

Base URL: `http://localhost/expedia/api/`

### Get your API token (login)
```
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@expedia.ph",
  "password": "Admin@1234"
}
```
Response:
```json
{
  "success": true,
  "data": {
    "token": "abc123...",
    "user": { "id": 1, "role": "admin", ... }
  }
}
```

Use the token in all protected requests:
```
Authorization: Bearer abc123...
```
Or append to URL: `?api_key=abc123...`

---

### Public endpoints (no token needed)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/hotels` | List all hotels |
| GET | `/api/hotels?location_id=3` | Filter by location |
| GET | `/api/hotels?stars=5` | Filter by star rating |
| GET | `/api/hotels?sort=price_asc` | Sort results |
| GET | `/api/hotels/{id}` | Hotel detail + rooms + reviews |
| GET | `/api/rooms` | List all rooms |
| GET | `/api/rooms?hotel_id=1` | Rooms for a hotel |
| GET | `/api/rooms/{id}` | Single room detail |
| GET | `/api/locations` | All locations |

---

### Protected user endpoints (token required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/bookings` | My bookings |
| GET | `/api/bookings/{id}` | Single booking |
| POST | `/api/bookings` | Create a booking |
| PUT | `/api/bookings/{id}/cancel` | Cancel a booking |

**Create booking body:**
```json
{
  "room_id": 1,
  "hotel_id": 1,
  "check_in": "2025-07-01",
  "check_out": "2025-07-05",
  "guests": 2,
  "pay_method": "gcash",
  "special_requests": "High floor please"
}
```

---

### Admin-only endpoints (admin token required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/hotels` | Create hotel |
| PUT | `/api/hotels/{id}` | Update hotel |
| DELETE | `/api/hotels/{id}` | Delete hotel |
| GET | `/api/admin/bookings` | All bookings |
| GET | `/api/admin/bookings?status=confirmed` | Filter bookings |
| GET | `/api/admin/payments` | All payments |
| GET | `/api/admin/stats` | Dashboard stats |

---

### Test the API in your browser (no tools needed)

Public endpoints work directly in a browser tab:
```
http://localhost/expedia/api/hotels
http://localhost/expedia/api/hotels/1
http://localhost/expedia/api/locations
http://localhost/expedia/api/rooms?hotel_id=1
```

For POST/PUT/DELETE, use Postman or Insomnia (free apps).

---

## What's fully working

### User features
- ✅ Register / Login / Logout (session-based)
- ✅ Browse and search hotels (filter by location, stars, price, sort)
- ✅ View hotel detail with rooms and reviews
- ✅ 2-step booking flow (trip details → payment)
- ✅ 6 payment methods: Credit card, Debit card, GCash, Maya, Bank transfer, Pay at hotel
- ✅ My Trips page — view all bookings with payment info
- ✅ Cancel a confirmed booking

### Admin features (full CRUD)
- ✅ Hotels: Create, Read, Update, Delete, Toggle active/inactive
- ✅ Rooms: Create, Read, Update, Delete per hotel
- ✅ Bookings: Read all, Update status, Delete
- ✅ Payments: Read all, Issue refunds, Filter by method
- ✅ Users: Read all, Change role (user ↔ admin), Delete

### REST API
- ✅ Public: hotels, rooms, locations (no auth)
- ✅ Token auth via login endpoint
- ✅ User: create booking, view bookings, cancel booking
- ✅ Admin: full hotel CRUD, all bookings, all payments, stats

---

## Troubleshooting

**White screen / fatal error**
→ Check XAMPP is running (both Apache AND MySQL green)
→ Make sure the folder is named `expedia` (not `final` or `expedia2`)

**Database connection failed**
→ MySQL is not running, or you haven't imported the SQL yet
→ If you set a MySQL root password, open `includes/config.php` and set `DB_PASS`

**Admin login not working**
→ Run this in phpMyAdmin SQL tab:
```sql
UPDATE users SET password='$2y$10$TKh8H1.PfYi1LrJFQbOIW.bwPKN5TCxGO2Y3KZB7lC1I0OHMqj0ly' WHERE email='admin@expedia.ph';
```

**Booking error after payment**
→ You have an old database. Drop it and reimport `expedia_ph_FINAL.sql` from scratch (Steps 3–4 above).

**API returns 404**
→ Make sure mod_rewrite is enabled in XAMPP. Open XAMPP → Apache → Config → httpd.conf, find `#LoadModule rewrite_module` and remove the `#`.
