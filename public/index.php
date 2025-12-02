<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_login();
$user = current_user();
$pdo = get_db();

$stmt = $pdo->prepare('SELECT p.*, COUNT(e.id) as episode_count FROM projects p LEFT JOIN episodes e ON e.project_id = p.id WHERE p.owner_user_id = :uid OR p.is_public = 1 GROUP BY p.id ORDER BY p.created_at DESC');
$stmt->execute([':uid' => $user['id']]);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Projects - WritersBlocks</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header class="topbar">
        <div>Welcome, <?php echo h($user['email']); ?></div>
        <div>
            <a href="index.php">Projects</a> |
            <a href="logout.php">Logout</a>
        </div>
    </header>
    <main>
        <h1>Projects</h1>
        <?php foreach ($projects as $project): ?>
            <div class="card">
                <h2><a href="project.php?project_id=<?php echo $project['id']; ?>"><?php echo h($project['title']); ?></a></h2>
                <p><?php echo h($project['description'] ?? ''); ?></p>
                <div class="meta">Visibility: <?php echo $project['is_public'] ? 'Public' : 'Private'; ?> | Episodes: <?php echo $project['episode_count']; ?></div>
            </div>
        <?php endforeach; ?>
    </main>
</body>

</html>