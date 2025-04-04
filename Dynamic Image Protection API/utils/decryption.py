import numpy as np

def decrypt_image(encrypted_image, seed=0.54321, r=3.99):
    rows, cols, channels = encrypted_image.shape
    total_pixels = rows * cols * channels
    from utils.encryption import logistic_map
    chaotic_seq = logistic_map(seed, total_pixels, r).reshape(rows, cols, channels)
    decrypted_image = np.bitwise_xor(encrypted_image.astype(np.uint8), chaotic_seq.astype(np.uint8))
    return decrypted_image