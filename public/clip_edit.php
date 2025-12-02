<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_login();
$user = current_user();
$pdo = get_db();

$clipId = isset($_GET['clip_id']) ? (int)$_GET['clip_id'] : 0;
$sceneId = isset($_GET['scene_id']) ? (int)$_GET['scene_id'] : 0;
$episodeId = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$clip = null;

if ($clipId) {
    $stmt = $pdo->prepare('SELECT c.*, s.act_id, a.episode_id, p.owner_user_id, p.is_public FROM clips c JOIN scenes s ON s.id = c.scene_id JOIN acts a ON a.id = s.act_id JOIN episodes e ON e.id = a.episode_id JOIN projects p ON p.id = e.project_id WHERE c.id = :id');
    $stmt->execute([':id' => $clipId]);
    $clip = $stmt->fetch();
    if (!$clip) {
        echo 'Clip not found';
        exit;
    }
    $sceneId = (int)$clip['scene_id'];
    $episodeId = (int)$clip['episode_id'];
    $project = ['owner_user_id' => $clip['owner_user_id'], 'is_public' => $clip['is_public']];
    if (!user_can_access_project($project, $user)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
} else {
    $sceneStmt = $pdo->prepare('SELECT s.*, a.episode_id, p.owner_user_id, p.is_public FROM scenes s JOIN acts a ON a.id = s.act_id JOIN episodes e ON e.id = a.episode_id JOIN projects p ON p.id = e.project_id WHERE s.id = :id');
    $sceneStmt->execute([':id' => $sceneId]);
    $scene = $sceneStmt->fetch();
    if (!$scene) {
        echo 'Scene not found';
        exit;
    }
    $episodeId = (int)$scene['episode_id'];
    $project = ['owner_user_id' => $scene['owner_user_id'], 'is_public' => $scene['is_public']];
    if (!user_can_access_project($project, $user)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $duration = (int)($_POST['duration_seconds'] ?? 0);
    $sora = trim($_POST['sora_url'] ?? '');
    $uploaded = trim($_POST['uploaded_url'] ?? '');
    $position = (int)($_POST['position'] ?? 1);
    if (isset($_POST['delete']) && $clipId) {
        $del = $pdo->prepare('DELETE FROM clips WHERE id = :id');
        $del->execute([':id' => $clipId]);
    } elseif ($clipId) {
        $update = $pdo->prepare('UPDATE clips SET title = :title, duration_seconds = :duration, sora_url = :sora, uploaded_url = :uploaded, position = :position WHERE id = :id');
        $update->execute([
            ':title' => $title,
            ':duration' => $duration,
            ':sora' => $sora ?: null,
            ':uploaded' => $uploaded ?: null,
            ':position' => $position,
            ':id' => $clipId,
        ]);
    } else {
        $insert = $pdo->prepare('INSERT INTO clips (scene_id, title, duration_seconds, sora_url, uploaded_url, position) VALUES (:scene_id, :title, :duration, :sora, :uploaded, :position)');
        $insert->execute([
            ':scene_id' => $sceneId,
            ':title' => $title,
            ':duration' => $duration,
            ':sora' => $sora ?: null,
            ':uploaded' => $uploaded ?: null,
            ':position' => $position,
        ]);
    }
    header('Location: episode.php?episode_id=' . $episodeId);
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo $clipId ? 'Edit Clip' : 'Add Clip'; ?> - WritersBlocks</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header class="topbar">
        <div><a href="episode.php?episode_id=<?php echo $episodeId; ?>">&larr; Back</a></div>
        <div><a href="logout.php">Logout</a></div>
    </header>
    <main>
        <h1><?php echo $clipId ? 'Edit Clip' : 'Add Clip'; ?></h1>
        <form method="post" class="form">
            <label>Title<br>
                <input type="text" name="title" required value="<?php echo h($clip['title'] ?? ''); ?>">
            </label>
            <label>Duration (seconds)<br>
                <input type="number" name="duration_seconds" min="0" value="<?php echo h($clip['duration_seconds'] ?? 0); ?>">
            </label>
            <label>Sora URL<br>
                <input type="url" name="sora_url" value="<?php echo h($clip['sora_url'] ?? ''); ?>">
            </label>
            <label>Uploaded URL<br>
                <input type="url" name="uploaded_url" value="<?php echo h($clip['uploaded_url'] ?? ''); ?>">
            </label>
            <label>Position<br>
                <input type="number" name="position" min="1" value="<?php echo h($clip['position'] ?? 1); ?>">
            </label>
            <button type="submit">Save</button>
            <?php if ($clipId): ?>
                <button type="submit" name="delete" value="1" class="danger" onclick="return confirm('Delete clip?');">Delete</button>
            <?php endif; ?>
        </form>
    </main>
</body>

</html>