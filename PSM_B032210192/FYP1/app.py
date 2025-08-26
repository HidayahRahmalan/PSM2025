from flask import Flask, render_template, request, redirect, flash, session
import mysql.connector
from datetime import datetime

app = Flask(__name__)
app.secret_key = 'your_secret_key'

# Connect to MySQL (WAMP)
def get_db_connection():
    return mysql.connector.connect(
        host='localhost',
        user='root',
        password='',  # use your MySQL password if any
        database='rytcms'
    )

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        name = request.form['name']
        email = request.form['email']
        phone = request.form['phone']
        password = request.form['password']
        created_date = datetime.now()

        # Check if the email already exists
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT * FROM customer WHERE CUST_EMAIL = %s", (email,))
        existing_user = cursor.fetchone()

        if existing_user:
            flash("Email already registered. Please log in.", "danger")
            return redirect('/login')

        # Insert into CUSTOMER table
        sql = """INSERT INTO customer (CUST_ID,CUST_NAME, CUST_EMAIL, CUST_PASSWORD, CUST_PHONE, CUST_CREATED_DATE)
                 VALUES (%s, %s,%s, %s, %s, %s)"""
        val = (name, email, password, phone, created_date)
        cursor.execute(sql, val)
        conn.commit()
        cursor.close()
        conn.close()

        flash("Registration successful!", "success")
        return redirect('/login')

    return render_template('register.html')


@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        email = request.form['email']
        password = request.form['password']

        # Check credentials in the database
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT CUST_ID, CUST_NAME, CUST_EMAIL, CUST_PASSWORD FROM customer WHERE CUST_EMAIL = %s AND CUST_PASSWORD = %s", (email, password))
        user = cursor.fetchone()

        if user:
            # Store CUST_ID in session
            session['user_id'] = user[0]  # CUST_ID is the first column
            session['user_name'] = user[1]  # CUST_NAME is the second column (if you want to store the name too)

            flash("Login successful!", "success")
            return redirect('/customer_dashboard')
        else:
            flash("Invalid email or password. Please try again.", "danger")

        cursor.close()
        conn.close()

    return render_template('login.html')


@app.route('/Customer_Mainpage')
def Customer_Mainpage():
    if 'user_id' not in session:
        flash("You need to log in first.", "danger")
        return redirect('/login')

    # You can now access session['user_id'] (CUST_ID)
    user_id = session['user_id']
    return f"<h2>Welcome, Customer {user_id}!</h2>"


@app.route('/logout')
def logout():
    session.pop('user_id', None)  # Remove user ID from session
    session.pop('user_name', None)  # Optionally remove user name
    flash("You have been logged out.", "info")
    return redirect('/login')


if __name__ == '__main__':
    app.run(debug=True)
