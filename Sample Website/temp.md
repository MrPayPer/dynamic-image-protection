
**Script for Showing the images in the gallery**
<?php foreach ($images as $row): ?>
                    <div class="card">
                        <form method="post" class="delete-form">
                            <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn-delete">🗑 Delete</button>
                        </form>
                        <img src="<?= htmlspecialchars($row['image_path']) ?>" alt="Image">
                    </div>
                <?php endforeach; ?>