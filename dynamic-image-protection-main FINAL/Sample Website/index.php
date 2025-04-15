<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = '';

// Fetch username
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username);
$stmt->fetch();
$stmt->close();

// Handle image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $image_id = $_POST['delete_id'];

    $stmt = $conn->prepare("SELECT image_path FROM images WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $image_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($image_path);
    $stmt->fetch();
    $stmt->close();

    if ($image_path && file_exists($image_path)) {
        unlink($image_path);
    }

    $stmt = $conn->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $image_id, $user_id);
    $stmt->execute();

    header("Location: index.php");
    exit;
}

// Fetch images into array
$stmt = $conn->prepare("SELECT id, image_path FROM images WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$images = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Image Gallery</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">Image Gallery</div>
    <div class="navbar-links">
        <a href="upload.php">Upload</a>
        <!-- Button with JavaScript redirection in a new tab -->
        <button id="toggleDashboardBtn" class="dashboard-toggle" onclick="window.open('dashboard.php', '_blank')">🛠 Dashboard</button>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</nav>
    <main>
        <h2>Welcome, <?= htmlspecialchars($username) ?></h2>

        <?php if (empty($images)): ?>
            <h3 class="subheading">No uploaded images</h3>
        <?php else: ?>
            <h3 class="subheading">Images Uploaded</h3>
            <div class="gallery">
                <?php foreach ($images as $row): ?>
                    <?php
                        $filename = basename($row['image_path'], '.png');
                        $decrypted_url = "http://127.0.0.1:5050/api/decrypt-image/" . $filename;
                    ?>
                    <div class="card">
                        <form method="post" class="delete-form">
                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-delete">🗑 Delete</button>
                        </form>
                        <img src="<?= $decrypted_url ?>" alt="Decrypted Image">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        &copy; 2025 Image Gallery
    </footer>

    <!-- Scripts -->
    <script src="http://localhost:5050/api/scripts/security.js"></script>
</body>
</html>