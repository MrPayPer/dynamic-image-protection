from flask import Flask, request, jsonify, send_file, send_from_directory
import os
import cv2
import numpy as np
import sqlite3
import time
import io
from werkzeug.utils import secure_filename
from utils.encryption import encrypt_image, generate_safe_filename
from utils.decryption import decrypt_image
from flask_cors import CORS
import logging
from utils.detection import detect_and_rate_limit, fingerprint_from_headers

logging.basicConfig(filename='logs/scraping_logs.txt', level=logging.INFO)

app = Flask(__name__)
CORS(app)

UPLOAD_FOLDER = 'static/encrypted_images'
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# --- Database Setup ---
DB_PATH = 'Dynamic Image Protection API\database\images.db'

def init_db():
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS images (
            image_id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            original_filename TEXT,
            encrypted_filename TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        )
    ''')
    conn.commit()
    conn.close()

init_db()

def save_image_record(user_id, original_filename, encrypted_filename):
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    cursor.execute('''
        INSERT INTO images (user_id, original_filename, encrypted_filename, created_at)
        VALUES (?, ?, ?, ?)
    ''', (user_id, original_filename, encrypted_filename, time.time()))
    conn.commit()
    conn.close()

# --- Global Defense Layer ---
@app.before_request
def global_defense_layer():
    detect_and_rate_limit()
    fingerprint_from_headers()

# --- API ROUTES ---

@app.route('/api/encrypt-image', methods=['POST'])
def encrypt_image_api():
    if 'file' not in request.files or 'user_id' not in request.form:
        return jsonify({'error': 'Missing file or user_id'}), 400

    file = request.files['file']
    user_id = request.form['user_id']
    filename = secure_filename(file.filename)

    # Read image and encrypt
    image = cv2.imdecode(np.frombuffer(file.read(), np.uint8), cv2.IMREAD_COLOR)
    encrypted_image = encrypt_image(image, seed=0.54321, r=3.99)

    # Generate safe filename and save encrypted image
    encrypted_filename = generate_safe_filename(filename)
    encrypted_path = os.path.join(app.config['UPLOAD_FOLDER'], encrypted_filename)
    cv2.imwrite(encrypted_path, encrypted_image, [cv2.IMWRITE_PNG_COMPRESSION, 0])

    # Save image record to DB
    save_image_record(user_id, filename, encrypted_filename)

    return jsonify({
        'message': 'Encrypted image saved successfully.',
        'encrypted_filename': encrypted_filename,
        'encrypted_image_url': f'/static/encrypted_images/{encrypted_filename}'
    })

@app.route('/api/decrypt-image/<filename>', methods=['GET'])
def decrypt_image_api(filename):
    encrypted_filename = filename + '.png'
    encrypted_path = os.path.join(app.config['UPLOAD_FOLDER'], encrypted_filename)

    if not os.path.exists(encrypted_path):
        return jsonify({'error': 'Encrypted file not found'}), 404

    encrypted_image = cv2.imread(encrypted_path, cv2.IMREAD_COLOR)
    if encrypted_image is None:
        return jsonify({'error': 'Unable to read encrypted image'}), 400

    # Decrypt and send from memory
    decrypted_image = decrypt_image(encrypted_image, seed=0.54321, r=3.99)
    _, buffer = cv2.imencode('.png', decrypted_image)
    return send_file(io.BytesIO(buffer.tobytes()), mimetype='image/png')

# Make it so that security.js can be loaded in the website via linking the script in the html structure
# <script src="http://localhost:5050/api/scripts/security.js"></script>
@app.route('/api/scripts/security.js')
def serve_security_js():
    return send_from_directory('static', 'security.js', mimetype='application/javascript')

# Error handler for rate limiting
@app.errorhandler(429)
def handle_rate_limit(e):
    return jsonify({'error': 'Too many requests'}), 429

# Run the app
if __name__ == '__main__':
    app.run(debug=True, port=5050)