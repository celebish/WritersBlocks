<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_login();
$user = current_user();
$pdo = get_db();

$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$stmt = $pdo->prepare('SELECT * FROM projects WHERE id = :id');
$stmt->execute([':id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    echo 'Project not found';
    exit;
}

if (!user_can_access_project($project, $user)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($title !== '') {
        $insert = $pdo->prepare('INSERT INTO episodes (project_id, title, description, created_at) VALUES (:project_id, :title, :description, :created_at)');
        $insert->execute([
            ':project_id' => $projectId,
            ':title' => $title,
            ':description' => $description,
            ':created_at' => date('c'),
        ]);
        header('Location: project.php?project_id=' . $projectId);
        exit;
    }
}

$episodesStmt = $pdo->prepare('SELECT * FROM episodes WHERE project_id = :project_id ORDER BY created_at DESC');
$episodesStmt->execute([':project_id' => $projectId]);
$episodes = $episodesStmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo h($project['title']); ?> - WritersBlocks</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header class="topbar">
        <div><a href="index.php">&larr; Projects</a></div>
        <div><a href="logout.php">Logout</a></div>
    </header>
    <main>
        <h1><?php echo h($project['title']); ?></h1>
        <p><?php echo h($project['description'] ?? ''); ?></p>
        <div class="meta">Visibility: <?php echo $project['is_public'] ? 'Public' : 'Private'; ?></div>

        <section class="section">
            <h2>Episodes</h2>
            <?php foreach ($episodes as $episode): ?>
                <?php $episodeRuntime = calculate_episode_runtime($pdo, (int)$episode['id']); ?>
                <div class="card">
                    <h3><a href="episode.php?episode_id=<?php echo $episode['id']; ?>"><?php echo h($episode['title']); ?></a></h3>
                    <p><?php echo h($episode['description'] ?? ''); ?></p>
                    <div class="meta">Runtime: <?php echo format_runtime($episodeRuntime); ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="section">
            <h2>Add Episode</h2>
            <form method="post" class="form-inline">
                <input type="text" name="title" placeholder="Episode title" required>
                <input type="text" name="description" placeholder="Description">
                <button type="submit">Create</button>
            </form>
        </section>
    </main>
</body>

</html>