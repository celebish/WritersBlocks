<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_login();
$user = current_user();
$pdo = get_db();

$sceneId = isset($_GET['scene_id']) ? (int)$_GET['scene_id'] : 0;
$actId = isset($_GET['act_id']) ? (int)$_GET['act_id'] : 0;
$episodeId = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$scene = null;

if ($sceneId) {
    $stmt = $pdo->prepare('SELECT s.*, a.episode_id, p.owner_user_id, p.is_public FROM scenes s JOIN acts a ON a.id = s.act_id JOIN episodes e ON e.id = a.episode_id JOIN projects p ON p.id = e.project_id WHERE s.id = :id');
    $stmt->execute([':id' => $sceneId]);
    $scene = $stmt->fetch();
    if (!$scene) {
        echo 'Scene not found';
        exit;
    }
    $actId = (int)$scene['act_id'];
    $episodeId = (int)$scene['episode_id'];
    $project = ['owner_user_id' => $scene['owner_user_id'], 'is_public' => $scene['is_public']];
    if (!user_can_access_project($project, $user)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
} else {
    $actStmt = $pdo->prepare('SELECT a.*, e.project_id, p.owner_user_id, p.is_public FROM acts a JOIN episodes e ON e.id = a.episode_id JOIN projects p ON p.id = e.project_id WHERE a.id = :id');
    $actStmt->execute([':id' => $actId]);
    $act = $actStmt->fetch();
    if (!$act) {
        echo 'Act not found';
        exit;
    }
    $episodeId = (int)$act['episode_id'];
    $project = ['owner_user_id' => $act['owner_user_id'], 'is_public' => $act['is_public']];
    if (!user_can_access_project($project, $user)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $position = (int)($_POST['position'] ?? 1);
    if (isset($_POST['delete']) && $sceneId) {
        $del = $pdo->prepare('DELETE FROM scenes WHERE id = :id');
        $del->execute([':id' => $sceneId]);
    } elseif ($sceneId) {
        $update = $pdo->prepare('UPDATE scenes SET title = :title, description = :description, position = :position WHERE id = :id');
        $update->execute([':title' => $title, ':description' => $description, ':position' => $position, ':id' => $sceneId]);
    } else {
        $insert = $pdo->prepare('INSERT INTO scenes (act_id, title, description, position) VALUES (:act_id, :title, :description, :position)');
        $insert->execute([':act_id' => $actId, ':title' => $title, ':description' => $description, ':position' => $position]);
    }
    header('Location: episode.php?episode_id=' . $episodeId);
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo $sceneId ? 'Edit Scene' : 'Add Scene'; ?> - WritersBlocks</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header class="topbar">
        <div><a href="episode.php?episode_id=<?php echo $episodeId; ?>">&larr; Back</a></div>
        <div><a href="logout.php">Logout</a></div>
    </header>
    <main>
        <h1><?php echo $sceneId ? 'Edit Scene' : 'Add Scene'; ?></h1>
        <form method="post" class="form">
            <label>Title<br>
                <input type="text" name="title" required value="<?php echo h($scene['title'] ?? ''); ?>">
            </label>
            <label>Description<br>
                <textarea name="description" rows="3"><?php echo h($scene['description'] ?? ''); ?></textarea>
            </label>
            <label>Position<br>
                <input type="number" name="position" min="1" value="<?php echo h($scene['position'] ?? 1); ?>">
            </label>
            <button type="submit">Save</button>
            <?php if ($sceneId): ?>
                <button type="submit" name="delete" value="1" class="danger" onclick="return confirm('Delete scene?');">Delete</button>
            <?php endif; ?>
        </form>
    </main>
</body>

</html>