#!/bin/bash
# Export the users table from SQLite database to CSV
sqlite3 database/database.sqlite <<EOF
.headers on
.mode csv
.output users_export.csv
SELECT * FROM users;
.quit
EOF
echo "Users table exported to users_export.csv"
