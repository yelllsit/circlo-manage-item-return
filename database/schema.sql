PRAGMA foreign_keys = ON;
CREATE TABLE IF NOT EXISTS students (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL, student_id TEXT NOT NULL UNIQUE,
 email TEXT NOT NULL COLLATE NOCASE UNIQUE, password_hash TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 owner_id INTEGER NOT NULL REFERENCES students(id),
 name TEXT NOT NULL, description TEXT NOT NULL, condition TEXT NOT NULL,
 image TEXT NOT NULL, pickup_location TEXT NOT NULL,
 availability TEXT NOT NULL CHECK(availability IN ('available','booked'))
);
CREATE TABLE IF NOT EXISTS transactions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 item_id INTEGER NOT NULL REFERENCES items(id),
 borrower_id INTEGER NOT NULL REFERENCES students(id),
 owner_id INTEGER NOT NULL REFERENCES students(id),
 start_date TEXT NOT NULL, end_date TEXT NOT NULL,
 total_price INTEGER NOT NULL DEFAULT 0 CHECK(total_price>=0),
 status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','completed')),
 completed_at TEXT,
 CHECK(end_date>=start_date), CHECK(borrower_id<>owner_id)
);
CREATE TABLE IF NOT EXISTS item_returns (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 transaction_id INTEGER NOT NULL UNIQUE REFERENCES transactions(id),
 return_date TEXT NOT NULL,
 status TEXT NOT NULL DEFAULT 'awaiting' CHECK(status IN ('awaiting','confirmed')),
 is_late INTEGER NOT NULL CHECK(is_late IN (0,1)),
 created_at TEXT NOT NULL, confirmed_at TEXT
);
CREATE INDEX IF NOT EXISTS active_item_transactions ON transactions(item_id,status);
