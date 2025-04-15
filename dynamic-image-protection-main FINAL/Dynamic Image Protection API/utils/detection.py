# utils/detection.py
import time, logging
from flask import request, abort

visitor_tracker = {}
MAX_VISITS = 20
TIME_FRAME = 60

# Set up logging
logging.basicConfig(filename='Dynamic Image Protection API/logs/scraping_logs.txt', level=logging.INFO)

def detect_and_rate_limit():
    ip = request.headers.get('X-Forwarded-For', request.remote_addr).split(',')[0].strip()
    now = time.time()

    visitor_tracker.setdefault(ip, [])
    visitor_tracker[ip] = [t for t in visitor_tracker[ip] if now - t < TIME_FRAME]

    print(f"[DEBUG] IP: {ip} - Request Count: {len(visitor_tracker[ip])}")
    logging.info(f"[ACCESS] IP {ip} - Count: {len(visitor_tracker[ip])}")

    if len(visitor_tracker[ip]) >= MAX_VISITS:
        logging.info(f"[RATE LIMIT] IP {ip} exceeded limit")
        print(f"[BLOCKED] IP {ip} hit the rate limit")
        abort(429)

    visitor_tracker[ip].append(now)

def fingerprint_from_headers():
    ip = request.headers.get('X-Forwarded-For', request.remote_addr).split(',')[0].strip()
    ua = request.headers.get('User-Agent', '')
    suspicious = []

    if "Headless" in ua or "python" in ua.lower():
        suspicious.append("Headless browser or script detected")
    if "curl" in ua.lower() or "wget" in ua.lower():
        suspicious.append("CLI tool detected")
    if not request.accept_languages:
        suspicious.append("Missing language settings")

    if suspicious:
        logging.info(f"[FINGERPRINT] IP {ip} - {', '.join(suspicious)}")