<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = '';

// =========================================================================================================

// This is the hardcoded code for the PHP backend with no connection to the DIP API
//      this will be commented out for when the site backend is connected to the DIP API
// if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["image"])) {
//     $targetDir = "uploads/";

//     // Create uploads/ folder if it doesn't exist
//     if (!is_dir($targetDir)) {
//         mkdir($targetDir, 0755, true);
//     }

//     $filename = basename($_FILES["image"]["name"]);
//     $uniqueName = time() . "_" . bin2hex(random_bytes(8)) . "_" . $filename;
//     $targetFile = $targetDir . $uniqueName;

//     // Move uploaded file
//     if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
//         $stmt = $conn->prepare("INSERT INTO images (user_id, image_path) VALUES (?, ?)");
//         $stmt->bind_param("is", $_SESSION['user_id'], $targetFile);
//         $stmt->execute();
//         header("Location: index.php");
//         exit;
//     } else {
//         $error = "Upload failed. Please check permissions or file size.";
//     }
// }

// =========================================================================================================


// CONNECTION TO DIP API
// This is for sending the image to the DIP API
// Currently untested if the image will be decrypted but initial testing shows that the API and
//      it's endpoints are working
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["image"])) {
    // Prepare cURL to send image to Flask API
    $apiUrl = "http://127.0.0.1:5050/api/encrypt-image";
    $userId = $_SESSION['user_id'];

    // Create a cURL file from the uploaded image
    $imageFile = new CURLFile($_FILES["image"]["tmp_name"], $_FILES["image"]["type"], $_FILES["image"]["name"]);

    $postFields = [
        'file' => $imageFile,
        'user_id' => $userId
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200) {
        $data = json_decode($response, true);
        $encryptedFilename = $data["encrypted_filename"];
        $encryptedPath = "static/encrypted_images/" . $encryptedFilename;

        // Save encrypted image info in your MySQL database
        $stmt = $conn->prepare("INSERT INTO images (user_id, image_path) VALUES (?, ?)");
        $stmt->bind_param("is", $userId, $encryptedPath);
        $stmt->execute();

        header("Location: index.php");
        exit;
    } else {
        $error = "Encryption failed. Check the API or uploaded file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Image</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">Image Gallery</div>
    <div class="navbar-links">
        <a href="index.php">Home</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<main>
    <h2>Upload New Image</h2>

    <form id="upload-form" method="post" enctype="multipart/form-data">
        <div class="upload-box" id="drop-area">
            <input type="file" name="image" id="fileElem" accept="image/*" required hidden>
            <label id="fileLabel">
                📁 Drag & drop or click to upload an image
            </label>

        </div>
        <button type="submit" class="btn-upload">Upload</button>
    </form>

    <?php if (!empty($error)): ?>
        <p style="color: red; margin-top: 1rem;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</main>

<footer>
    &copy; 2025 Image Gallery 
</footer>

<script>
    const dropArea = document.getElementById("drop-area");
    const fileInput = document.getElementById("fileElem");
    const label = document.getElementById("fileLabel");

    dropArea.addEventListener("click", () => fileInput.click());

    dropArea.addEventListener("dragover", (e) => {
        e.preventDefault();
        dropArea.classList.add("highlight");
    });

    dropArea.addEventListener("dragleave", () => {
        dropArea.classList.remove("highlight");
    });

    dropArea.addEventListener("drop", (e) => {
        e.preventDefault();
        dropArea.classList.remove("highlight");

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            // Show file name visually, but cannot assign to input directly
            label.textContent = `📁 Selected: ${files[0].name}`;

            // Optional workaround: simulate click for user to confirm file selection manually
            alert("Please click the box and select the file manually. Drag & drop preview only.");
        }
    });

    fileInput.addEventListener("change", () => {
        if (fileInput.files.length > 0) {
            label.textContent = `📁 Selected: ${fileInput.files[0].name}`;
        }
    });
</script>
<script src="http://localhost:5050/api/scripts/security.js"></script>
</body>
</html>
