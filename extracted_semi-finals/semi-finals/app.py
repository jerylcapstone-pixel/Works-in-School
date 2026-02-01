from flask import Flask, request, jsonify, render_template, send_from_directory
import os
from werkzeug.utils import secure_filename
from flask_mysqldb import MySQL
import logging

# Initialize Flask app
app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads/'
app.config['MYSQL_HOST'] = 'localhost'
app.config['MYSQL_USER'] = 'root'
app.config['MYSQL_PASSWORD'] = ''
app.config['MYSQL_DB'] = 'gadgets_db'

# Initialize MySQL
mysql = MySQL(app)

# Ensure upload folder exists
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# Enable logging
logging.basicConfig(level=logging.DEBUG)

# Serve uploaded photos
@app.route('/uploads/<filename>')
def uploaded_file(filename):
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename)

# Home route
@app.route('/')
def home():
    return render_template('index.html')

# Manage gadgets route
@app.route('/manage')
def manage_gadgets():
    return render_template('manage.html')

# API: Get all gadgets
@app.route('/api/gadgets', methods=['GET'])
def api_get_gadgets():
    try:
        cursor = mysql.connection.cursor()
        cursor.execute("SELECT * FROM gadgets")
        gadgets = cursor.fetchall()
        cursor.close()

        # Convert the data to a list of dictionaries for JSON serialization
        gadgets_list = []
        for gadget in gadgets:
            gadgets_list.append({
                'id': gadget[0],
                'name': gadget[1],
                'brand': gadget[2],
                'color': gadget[3],
                'price': float(gadget[4]),  # Ensure price is a float
                'photo': gadget[5]
            })

        return jsonify(gadgets_list)
    except Exception as e:
        logging.error(f"Error fetching gadgets: {e}")
        return jsonify({'error': str(e)}), 500

# API: Add a new gadget
@app.route('/api/gadgets', methods=['POST'])
def api_add_gadget():
    try:
        data = request.form
        photo = request.files['photo']

        if not photo:
            return jsonify({'error': 'Photo is required'}), 400

        # Save the uploaded photo
        photo_filename = secure_filename(photo.filename)
        photo_path = os.path.join(app.config['UPLOAD_FOLDER'], photo_filename)
        photo.save(photo_path)

        # Insert gadget into the database
        cursor = mysql.connection.cursor()
        cursor.execute(
            "INSERT INTO gadgets (name, brand, color, price, photo) VALUES (%s, %s, %s, %s, %s)",
            (data['name'], data['brand'], data['color'], data['price'], photo_filename)
        )
        mysql.connection.commit()
        gadget_id = cursor.lastrowid  # Get the last inserted ID
        cursor.close()

        return jsonify({
            'id': gadget_id,
            'name': data['name'],
            'brand': data['brand'],
            'color': data['color'],
            'price': data['price'],
            'photo': photo_filename
        }), 201
    except Exception as e:
        logging.error(f"Error adding gadget: {e}")
        return jsonify({'error': str(e)}), 500

# API: Get a single gadget by ID
@app.route('/api/gadgets/<int:gadget_id>', methods=['GET'])
def api_get_gadget(gadget_id):
    try:
        cursor = mysql.connection.cursor()
        cursor.execute("SELECT * FROM gadgets WHERE id=%s", (gadget_id,))
        gadget = cursor.fetchone()
        cursor.close()
        if gadget:
            return jsonify(gadget)
        else:
            return jsonify({'error': 'Gadget not found'}), 404
    except Exception as e:
        logging.error(f"Error fetching gadget: {e}")
        return jsonify({'error': str(e)}), 500

# API: Update a gadget
@app.route('/api/gadgets/<int:gadget_id>', methods=['POST'])
def api_edit_gadget(gadget_id):
    try:
        data = request.form
        photo = request.files.get('photo')

        cursor = mysql.connection.cursor()
        if photo:
            # Save the new photo
            photo_filename = secure_filename(f"gadget_{gadget_id}.jpg")
            photo_path = os.path.join(app.config['UPLOAD_FOLDER'], photo_filename)
            photo.save(photo_path)

            # Update gadget with new photo
            cursor.execute(
                "UPDATE gadgets SET name=%s, brand=%s, color=%s, price=%s, photo=%s WHERE id=%s",
                (data['name'], data['brand'], data['color'], data['price'], photo_filename, gadget_id)
            )
        else:
            # Update gadget without changing the photo
            cursor.execute(
                "UPDATE gadgets SET name=%s, brand=%s, color=%s, price=%s WHERE id=%s",
                (data['name'], data['brand'], data['color'], data['price'], gadget_id)
            )
        mysql.connection.commit()
        cursor.close()

        return jsonify({'message': 'Gadget updated successfully'})
    except Exception as e:
        logging.error(f"Error updating gadget: {e}")
        return jsonify({'error': str(e)}), 500

# API: Delete a gadget
@app.route('/api/gadgets/<int:gadget_id>', methods=['DELETE'])
def api_delete_gadget(gadget_id):
    try:
        cursor = mysql.connection.cursor()
        cursor.execute("DELETE FROM gadgets WHERE id=%s", (gadget_id,))
        mysql.connection.commit()
        cursor.close()
        return jsonify({'message': 'Gadget deleted successfully'})
    except Exception as e:
        logging.error(f"Error deleting gadget: {e}")
        return jsonify({'error': str(e)}), 500

# Search gadgets
@app.route('/search', methods=['GET'])
def search_gadgets():
    try:
        query = request.args.get('query', '').lower()
        cursor = mysql.connection.cursor()
        cursor.execute("SELECT * FROM gadgets WHERE LOWER(name) LIKE %s OR LOWER(brand) LIKE %s", (f"%{query}%", f"%{query}%"))
        gadgets = cursor.fetchall()
        cursor.close()
        return jsonify(gadgets)
    except Exception as e:
        logging.error(f"Error searching gadgets: {e}")
        return jsonify({'error': str(e)}), 500

# Run the app
if __name__ == '__main__':
    app.run(debug=True)