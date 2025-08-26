# db_connection.py

import mysql.connector

def get_db_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",
        password="",  # Default WAMP password (leave empty if none)
        database="rytcms"
    )
