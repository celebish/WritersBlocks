<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_login();
$user = current_user();
$pdo = get_db();

$actId = isset($_GET['act_id']) ? (int)$_GET['act_id'] : 0;
$episodeId = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$act = null;

if ($actId) {
    $stmt = $pdo->prepare('SELECT a.*, e.project_id, p.owner_user_id, p.is_public FROM acts a JOIN episodes e ON e.id = a.episode_id JOIN projects p ON p.id = e.project_id WHERE a.id = :id');
    $stmt->execute([':id' => $actId]);
    $act = $stmt->fetch();
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
} else {
    $episodeStmt = $pdo->prepare('SELECT e.*, p.owner_user_id, p.is_public FROM episodes e JOIN projects p ON p.id = e.project_id WHERE e.id = :id');
    $episodeStmt->execute([':id' => $episodeId]);
    $episode = $episodeStmt->fetch();
    if (!$episode) {
        echo 'Episode not found';
        exit;
    }
    $project = ['owner_user_id' => $episode['owner_user_id'], 'is_public' => $episode['is_public']];
    if (!user_can_access_project($project, $user)) {
        http_response_code(403);
        echo 'Access denied';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $position = (int)($_POST['position'] ?? 1);
    if (isset($_POST['delete']) && $actId) {
        $del = $pdo->prepare('DELETE FROM acts WHERE id = :id');
        $del->execute([':id' => $actId]);
    } elseif ($actId) {
        $update = $pdo->prepare('UPDATE acts SET title = :title, position = :position WHERE id = :id');
        $update->execute([':title' => $title, ':position' => $position, ':id' => $actId]);
    } else {
        $insert = $pdo->prepare('INSERT INTO acts (episode_id, title, position) VALUES (:episode_id, :title, :position)');
        $insert->execute([':episode_id' => $episodeId, ':title' => $title, ':position' => $position]);
    }
    header('Location: episode.php?episode_id=' . $episodeId);
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo $actId ? 'Edit Act' : 'Add Act'; ?> - WritersBlocks</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header class="topbar">
        <div><a href="episode.php?episode_id=<?php echo $episodeId; ?>">&larr; Back</a></div>
        <div><a href="logout.php">Logout</a></div>
    </header>
    <main>
        <h1><?php echo $actId ? 'Edit Act' : 'Add Act'; ?></h1>
        <form method="post" class="form">
            <label>Title<br>
                <input type="text" name="title" required value="<?php echo h($act['title'] ?? ''); ?>">
            </label>
            <label>Position<br>
                <input type="number" name="position" min="1" value="<?php echo h($act['position'] ?? 1); ?>">
            </label>
            <button type="submit">Save</button>
            <?php if ($actId): ?>
                <button type="submit" name="delete" value="1" class="danger" onclick="return confirm('Delete act?');">Delete</button>
            <?php endif; ?>
        </form>
    </main>
</body>

</html>