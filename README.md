# APRide — Smart Campus Carpool

APRide is a carpooling web app for university students and staff. Drivers offer rides around campus, passengers request seats and pay from an in-app wallet, and everyone can see how much CO₂ they've saved by sharing rides. Admins approve drivers and monitor bookings, revenue and environmental impact.

Built with plain PHP, MySQL/MariaDB and vanilla JavaScript. No framework or build step is needed.

## Features

**Passengers**
- Search upcoming rides by pick-up point, destination and date
- View ride details with a map of the pick-up and drop-off points
- Request a seat. The fare comes out of the wallet only when the driver accepts.
- Cancel a request before the driver responds
- Top up the wallet (demo payment form; no real card is charged)
- Earn 50 reward points per accepted ride and redeem them for rewards
- Track lifetime CO₂ savings

**Drivers** (after admin approval)
- Offer rides between fixed campus locations, with a live route preview
- Accept or decline passenger requests
- Cancel upcoming rides. Paid passengers are refunded automatically.
- See earnings per ride, eco points, an eco level and CO₂ saved

**Admins**
- Approve, reject, revoke or delete driver accounts
- Manage customers
- Reports for ride history, bookings and payments (with revenue), and CO₂ savings

The pages work on desktop, tablet and phone screens.

## Tech stack

| Part | Used |
|---|---|
| Backend | PHP 7.4+ (tested on PHP 8.3) with `mysqli` (needs the `mysqlnd` driver, included in standard PHP builds) |
| Database | MariaDB 10.5+ or MySQL 8 (tested on MariaDB 11.5) |
| Frontend | HTML, CSS, vanilla JavaScript |
| Maps | [Leaflet](https://leafletjs.com/) 1.9.4 with OpenStreetMap tiles, loaded from a CDN |

## Getting started

### 1. Install a local PHP + MySQL server
Any of these works: [WampServer](https://www.wampserver.com/), [XAMPP](https://www.apachefriends.org/), [Laragon](https://laragon.org/) or MAMP.

### 2. Put the project in the web root
Clone or copy the project into your server's web folder:

```bash
# WampServer: C:\wamp64\www   |   XAMPP: C:\xampp\htdocs
git clone <your-repo-url> apride
```

### 3. Create the database
In phpMyAdmin, or with the `mysql` command line:

1. Create a database named **`apride`** (collation `utf8mb4_unicode_ci`).
2. Import **`apride.sql`**. This creates the tables and sample data.
3. Import **`fix_database.sql`**. This is required: it fixes the schema (wallet decimals, InnoDB tables for transactions, driver statuses and so on) and repairs the sample data.

```bash
mysql -u root -p -e "CREATE DATABASE apride CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p apride < apride.sql
mysql -u root -p apride < fix_database.sql
```

> `add_wallet_column.sql` is an old script and is not needed.

### 4. Configure the connection
Edit **`db.php`** to match your database server:

```php
$servername = "127.0.0.1";
$port = 3307;        // WampServer's MariaDB uses 3307; XAMPP/MySQL usually use 3306
$username = "root";
$password = "";
$dbname = "apride";
```

### 5. Open the app
Go to **http://localhost/apride/**. You'll be taken to the login page.

## Demo accounts

These come from the sample data in `apride.sql`:

| Role | TP Number | Password | Notes |
|---|---|---|---|
| Admin | `jdoe` | `admin` | |
| Admin | `asmith` | `admin` | |
| Passenger | `TP081989` | `123456` | Has wallet balance and ride history |
| Passenger | `mcust` / `bcust` | `customer` | |
| Driver | `TP081987` | `1234` | Approved, so can offer rides straight away |
| Driver | `tdrive` / `sdrive` | `driver` | Pending: approve them as an admin first |
| Driver | `TP083435` | `1234` | Pending: approve them as an admin first |

The sample passwords are stored as plain text in `apride.sql`. Each one is converted to a secure hash automatically the first time that account logs in. New accounts are hashed from the start.

Most sample rides are dated in the past, so **Find Ride may be empty**. Log in as a driver and offer a new ride to try the booking flow.

## How it works

### Booking flow
1. A passenger opens a ride and taps **Request seat**. The booking is saved as *Pending*, and nothing is charged yet.
2. The driver sees the request under **My Rides** and accepts or declines it.
3. On **accept**, the fare is taken from the passenger's wallet, one seat is used and the passenger gets 50 points. These steps run together in one database transaction.
4. If the driver **cancels** an upcoming ride, paid passengers get their fare back and lose the points from that ride.

### CO₂ and eco stats
- CO₂ saved = distance (km) × **0.2 kg** × paying passengers, counted only for rides that have already happened.
- Tree equivalent: one mature tree absorbs about **21 kg** of CO₂ per year.
- Drivers earn **10 eco points + 10 per passenger** for each completed ride. Levels are Eco-Starter (0), Eco-Explorer (200), Eco-Warrior (500) and Eco-Legend (1000).

### Configuration
Business rules live at the top of `includes/bootstrap.php`:
- `CO2_KG_PER_PASSENGER_KM`, `TREE_KG_PER_YEAR` and `POINTS_PER_RIDE`
- `TOPUP_MIN` / `TOPUP_MAX`, the wallet top-up limits
- `LOCATIONS`, the pick-up and drop-off points drivers can choose, with map coordinates
- `VEHICLE_SEATS`, the maximum passengers per vehicle type

The app's timezone is set to `Asia/Kuala_Lumpur` in the same file.

## Project structure

```
├── includes/
│   ├── bootstrap.php      # session, DB helpers, auth/roles, CSRF, business rules
│   └── layout.php         # shared header/nav, footer, tiles, page titles
├── db.php                 # database connection settings
├── app.css                # all styles
├── admin.js               # helper for admin actions (approve/reject/delete)
├── apride.sql             # schema + sample data
├── fix_database.sql       # required schema/data fixes (run after apride.sql)
│
├── login.php, login_process.php, signup.php, logout.php
├── customer.php, find_ride.php, ride_details.php, payment.php,
│   booking_request.php, topup.php, rewards.php, co2_stats.php      # passenger
├── driver_dashboard.php, offer_ride.php, manage_ride.php (my_rides.php),
│   ride_history.php, earnings.php, profile.php                     # driver
└── admin.php, admin_approval.php, customer_list.php, view_drivers.php,
    transaction_report.php, co2_report.php, ride_history.php,
    update_driver_status.php, delete_customer.php, delete_driver.php # admin
```

## Security

- Every database query uses prepared statements.
- Passwords are hashed with `password_hash()`.
- Every page checks the user's role. Admin actions also require a logged-in admin and a CSRF token.
- Forms that change data are protected against CSRF.
- All output is escaped to prevent XSS.

The wallet top-up is a demo: card details are checked for format only and are never stored or charged. Before deploying anywhere public:
- give the database user a password
- turn off `display_errors` in `php.ini`
- connect a real payment gateway

## Troubleshooting

| Problem | Fix |
|---|---|
| `Connection failed` | Check the port and credentials in `db.php`, and make sure MySQL/MariaDB is running. |
| `Unknown collation 'utf8mb4_0900_ai_ci'` when importing | Your server is older MariaDB or MySQL. In `apride.sql`, replace `utf8mb4_0900_ai_ci` with `utf8mb4_unicode_ci` and import again. |
| `Field 'wallet' doesn't have a default value` on sign-up | `fix_database.sql` hasn't been run. Import it. |
| Pending drivers can't log in | This is expected. An admin has to approve them under **Requests**. |
| Find Ride shows no rides | The sample rides are in the past. Offer a new one as a driver. |
| Map doesn't show | The map loads from the internet (unpkg.com and OpenStreetMap), so check your connection. |

## License

Released under the [MIT License](LICENSE). © 2025 khavi-06
