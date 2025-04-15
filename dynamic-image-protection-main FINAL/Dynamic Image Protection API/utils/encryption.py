import numpy as np
import cv2
import hashlib
import time


# Logistic Map for Image Encryption
def logistic_map(seed, length, r=3.99):
    chaotic_seq = np.zeros(length, dtype=np.uint8)
    x = seed
    for i in range(length):
        x = r * x * (1 - x)
        chaotic_seq[i] = int((x * 255) % 256)
    return chaotic_seq

# Image Encryption Algorithm
# Might add 2 passes to increase encryption strength
def encrypt_image(image, seed=0.54321, r=3.99):
    rows, cols, channels = image.shape
    total_pixels = rows * cols * channels
    chaotic_seq = logistic_map(seed, total_pixels, r).reshape(rows, cols, channels)
    encrypted_image = np.bitwise_xor(image.astype(np.uint8), chaotic_seq.astype(np.uint8))
    return encrypted_image


# Rename the original file name in to a hashed string
def generate_safe_filename(original_name):
    name = original_name.encode('utf-8')
    timestamp = str(time.time()).encode('utf-8')
    hashed = hashlib.sha256(name + timestamp).hexdigest()
    return hashed + ".png"