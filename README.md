# La Casa Gaudencia — Backend API

Symfony 7.3 backend powering the La Casa Gaudencia hospitality management system.

---

## Requirements

- PHP 8.2+
- Composer
- MySQL 8.0
- Docker & Docker Compose (for database and Mercure hub)
- Symfony CLI

---

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Start Docker services (MySQL + phpMyAdmin + Mercure)
docker-compose up -d

# 3. Copy and configure environment
cp .env .env.local
# Edit .env.local — set DATABASE_URL, JWT keys, Firebase credentials

# 4. Generate JWT keys
php bin/console lexik:jwt:generate-keypair

# 5. Run database migrations
php bin/console doctrine:migrations:migrate

# 6. Start the development server (allow mobile devices on the same network)
symfony server:start

# 7. Start server for local network access (mobile testing)
php -S 0.0.0.0:8000 -t public
```

**Web admin panel:** `http://localhost:8000`  
**API docs (Swagger):** `http://localhost:8000/api/docs`  
**phpMyAdmin:** `http://localhost:8080`

---

## Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Login with username + password → returns JWT |
| POST | `/api/register` | Register new guest account |
| POST | `/api/auth/google` | Google OAuth login → returns JWT |

**Using the JWT token:**
```
Authorization: Bearer <token>
```

---

## API Endpoints

### Public (no auth required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/services/rooms` | List available rooms (mobile-friendly format) |
| GET | `/api/services/tours` | List available tours |
| GET | `/api/services/food` | List available food items |
| GET | `/api/services/packages` | List active packages |
| GET | `/api/rooms/{id}` | Room detail |
| GET | `/api/tours/{id}` | Tour detail |
| GET | `/api/foods/{id}` | Food item detail |
| GET | `/api/packages/{id}` | Package detail |

### Guest (JWT required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/reservations` | List own reservations |
| POST | `/api/reservations` | Create a reservation |
| GET | `/api/reservations/{id}` | Reservation detail |
| POST | `/api/payments` | Submit payment |
| GET | `/api/users/{id}` | Get own profile |
| PUT | `/api/users/{id}` | Update own profile |
| POST | `/api/contact_messages` | Submit contact/support message |

### Admin (JWT + ROLE_ADMIN)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/users` | List all users |
| DELETE | `/api/users/{id}` | Delete user |
| GET | `/api/payments` | List all payments |
| GET | `/api/activity_logs` | List audit logs |

---

## Example Requests

**Login**
```json
POST /api/login
{ "username": "guest1", "password": "password123" }

Response:
{ "token": "eyJ...", "user": { "id": 1, "username": "guest1", "roles": ["ROLE_GUEST"] } }
```

**Create Reservation (Room)**
```json
POST /api/reservations
Authorization: Bearer <token>
{
  "serviceType": "room",
  "guest": "/api/users/1",
  "room": "/api/rooms/3",
  "checkInDate": "2026-06-15",
  "checkOutDate": "2026-06-17",
  "numberOfGuests": 2,
  "contactPhone": "09171234567",
  "specialRequests": "Early check-in please"
}

Response: { "id": 42, "reservationCode": "RES-2026-000042", "status": "PENDING", ... }
```

**Create Reservation (Tour)**
```json
POST /api/reservations
Authorization: Bearer <token>
{
  "serviceType": "tour",
  "guest": "/api/users/1",
  "tour": "/api/tours/2",
  "tourDate": "2026-06-20",
  "tourParticipants": 3,
  "contactPhone": "09171234567"
}
```

**Submit Payment**
```json
POST /api/payments
Authorization: Bearer <token>
{
  "reservation": "/api/reservations/42",
  "amount": "5000.00",
  "paymentMethod": "GCASH",
  "referenceNumber": "GC-123456789",
  "guestNotes": "Paid via GCash"
}
```

---

## Roles

| Role | Access |
|------|--------|
| `ROLE_GUEST` | Browse services, manage own reservations/payments/profile |
| `ROLE_STAFF` | View all reservations, manage services |
| `ROLE_ADMIN` | Full access including user management and payment approval |

---

## Real-Time (Mercure)

The backend publishes reservation events to the Mercure hub topic `/topic/reservations` whenever a reservation is created or its status changes. The web dashboard subscribes automatically and shows a live toast notification.

---

## Docker Services

| Service | Port | Purpose |
|---------|------|---------|
| MySQL 8.0 | 3307 | Database |
| phpMyAdmin | 8080 | Database GUI |
| Mercure Hub | 3000 | Real-time push |
