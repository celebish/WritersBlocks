<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/helpers.php';
require_login();
$user = current_user();
$pdo = get_db();

$episodeId = isset($_GET['episode_id']) ? (int)$_GET['episode_id'] : 0;
$episodeStmt = $pdo->prepare('SELECT e.*, p.title AS project_title, p.owner_user_id, p.is_public, p.description AS project_description, p.id AS project_id FROM episodes e JOIN projects p ON p.id = e.project_id WHERE e.id = :id');
$episodeStmt->execute([':id' => $episodeId]);
$episode = $episodeStmt->fetch();

if (!$episode) {
    http_response_code(404);
    echo 'Episode not found';
    exit;
}

$project = [
    'id' => $episode['project_id'],
    'title' => $episode['project_title'],
    'owner_user_id' => $episode['owner_user_id'],
    'is_public' => $episode['is_public'],
];

if (!user_can_access_project($project, $user)) {
    http_response_code(403);
    echo 'Access denied';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_act') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM acts WHERE episode_id = :episode_id');
            $posStmt->execute([':episode_id' => $episodeId]);
            $nextPos = (int)$posStmt->fetch()['next_pos'];
            $insert = $pdo->prepare('INSERT INTO acts (episode_id, title, position) VALUES (:episode_id, :title, :position)');
            $insert->execute([
                ':episode_id' => $episodeId,
                ':title' => $title,
                ':position' => $nextPos,
            ]);
        }
        header('Location: episode.php?episode_id=' . $episodeId);
        exit;
    } elseif ($action === 'add_scene') {
        $actId = (int)($_POST['act_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($title !== '') {
            $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 AS next_pos FROM scenes WHERE act_id = :act_id');
            $posStmt->execute([':act_id' => $actId]);
            $nextPos = (int)$posStmt->fetch()['next_pos'];
            $insert = $pdo->prepare('INSERT INTO scenes (act_id, title, description, position) VALUES (:act_id, :title, :description, :position)');
            $insert->execute([
                ':act_id' => $actId,
                ':title' => $title,
                ':description' => $description,
                ':position' => $nextPos,
            ]);
        }
        header('Location: episode.php?episode_id=' . $episodeId);
        exit;
    } elseif ($action === 'clip_move') {
        $clipId = (int)($_POST['clip_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $clipStmt = $pdo->prepare('SELECT c.*, s.act_id, a.episode_id FROM clips c JOIN scenes s ON s.id = c.scene_id JOIN acts a ON a.id = s.act_id WHERE c.id = :id');
        $clipStmt->execute([':id' => $clipId]);
        $clip = $clipStmt->fetch();
        if ($clip && (int)$clip['episode_id'] === $episodeId) {
            if ($direction === 'up') {
                $neighborStmt = $pdo->prepare('SELECT * FROM clips WHERE scene_id = :scene_id AND position < :pos ORDER BY position DESC LIMIT 1');
            } else {
                $neighborStmt = $pdo->prepare('SELECT * FROM clips WHERE scene_id = :scene_id AND position > :pos ORDER BY position ASC LIMIT 1');
            }
            $neighborStmt->execute([':scene_id' => $clip['scene_id'], ':pos' => $clip['position']]);
            $neighbor = $neighborStmt->fetch();
            if ($neighbor) {
                $update = $pdo->prepare('UPDATE clips SET position = :pos WHERE id = :id');
                $update->execute([':pos' => $neighbor['position'], ':id' => $clip['id']]);
                $update->execute([':pos' => $clip['position'], ':id' => $neighbor['id']]);
            }
        }
        header('Location: episode.php?episode_id=' . $episodeId);
        exit;
    } elseif ($action === 'clip_delete') {
        $clipId = (int)($_POST['clip_id'] ?? 0);
        $delStmt = $pdo->prepare('DELETE FROM clips WHERE id = :id AND scene_id IN (SELECT s.id FROM scenes s JOIN acts a ON a.id = s.act_id WHERE a.episode_id = :episode_id)');
        $delStmt->execute([':id' => $clipId, ':episode_id' => $episodeId]);
        header('Location: episode.php?episode_id=' . $episodeId);
        exit;
    }
}

$actsStmt = $pdo->prepare('SELECT * FROM acts WHERE episode_id = :episode_id ORDER BY position ASC');
$actsStmt->execute([':episode_id' => $episodeId]);
$acts = $actsStmt->fetchAll();

$scenesStmt = $pdo->prepare('SELECT * FROM scenes WHERE act_id = :act_id ORDER BY position ASC');
$clipsStmt = $pdo->prepare('SELECT * FROM clips WHERE scene_id = :scene_id ORDER BY position ASC');
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title><?php echo h($episode['title']); ?> - WritersBlocks</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <header class="topbar">
        <div><a href="project.php?project_id=<?php echo $episode['project_id']; ?>">&larr; <?php echo h($episode['project_title']); ?></a></div>
        <div><a href="logout.php">Logout</a></div>
    </header>
    <main>
        <h1><?php echo h($episode['title']); ?></h1>
        <p><?php echo h($episode['description'] ?? ''); ?></p>
        <?php $episodeRuntime = calculate_episode_runtime($pdo, $episodeId); ?>
        <div class="meta">Episode runtime: <?php echo format_runtime($episodeRuntime); ?></div>

        <?php foreach ($acts as $act): ?>
            <?php $scenesStmt->execute([':act_id' => $act['id']]);
            $scenes = $scenesStmt->fetchAll(); ?>
            <?php $actRuntime = calculate_act_runtime($pdo, (int)$act['id']); ?>
            <div class="act">
                <div class="act-header" data-toggle="act-body-<?php echo $act['id']; ?>">
                    <strong>Act <?php echo h($act['position']); ?>:</strong> <?php echo h($act['title']); ?> — Runtime: <?php echo format_runtime($actRuntime); ?>
                    <span class="toggle">Toggle</span>
                    <span class="actions"><a href="act_edit.php?act_id=<?php echo $act['id']; ?>&episode_id=<?php echo $episodeId; ?>">Edit</a></span>
                </div>
                <div class="act-body" id="act-body-<?php echo $act['id']; ?>">
                    <?php foreach ($scenes as $scene): ?>
                        <?php $clipsStmt->execute([':scene_id' => $scene['id']]);
                        $clips = $clipsStmt->fetchAll(); ?>
                        <?php $sceneRuntime = calculate_scene_runtime($pdo, (int)$scene['id']); ?>
                        <div class="scene">
                            <div class="scene-header" data-toggle="scene-body-<?php echo $scene['id']; ?>">
                                <strong>Scene <?php echo h($scene['position']); ?>:</strong> <?php echo h($scene['title']); ?> — Runtime: <?php echo format_runtime($sceneRuntime); ?>
                                <span class="toggle">Toggle</span>
                                <span class="actions"><a href="scene_edit.php?scene_id=<?php echo $scene['id']; ?>&episode_id=<?php echo $episodeId; ?>">Edit</a></span>
                            </div>
                            <div class="scene-body" id="scene-body-<?php echo $scene['id']; ?>">
                                <p><?php echo h($scene['description'] ?? ''); ?></p>
                                <div class="clips">
                                    <?php foreach ($clips as $clip): ?>
                                        <div class="clip">
                                            <div class="clip-title"><?php echo h($clip['title']); ?></div>
                                            <div class="clip-meta">Duration: <?php echo format_runtime((int)$clip['duration_seconds']); ?></div>
                                            <?php if ($clip['sora_url']): ?><div class="clip-meta">Sora: <a href="<?php echo h($clip['sora_url']); ?>">Link</a></div><?php endif; ?>
                                            <?php if ($clip['uploaded_url']): ?><div class="clip-meta">Asset: <a href="<?php echo h($clip['uploaded_url']); ?>">Link</a></div><?php endif; ?>
                                            <div class="clip-actions">
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="action" value="clip_move">
                                                    <input type="hidden" name="clip_id" value="<?php echo $clip['id']; ?>">
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit">↑</button>
                                                </form>
                                                <form method="post" class="inline">
                                                    <input type="hidden" name="action" value="clip_move">
                                                    <input type="hidden" name="clip_id" value="<?php echo $clip['id']; ?>">
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit">↓</button>
                                                </form>
                                                <a href="clip_edit.php?clip_id=<?php echo $clip['id']; ?>&scene_id=<?php echo $scene['id']; ?>&episode_id=<?php echo $episodeId; ?>">Edit</a>
                                                <form method="post" class="inline" onsubmit="return confirm('Delete clip?');">
                                                    <input type="hidden" name="action" value="clip_delete">
                                                    <input type="hidden" name="clip_id" value="<?php echo $clip['id']; ?>">
                                                    <button type="submit" class="danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <a class="clip add" href="clip_edit.php?scene_id=<?php echo $scene['id']; ?>&episode_id=<?php echo $episodeId; ?>">+ Add Clip</a>
                                </div>
                                <div class="meta">Scene runtime: <?php echo format_runtime($sceneRuntime); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="add-form">
                        <form method="post">
                            <input type="hidden" name="action" value="add_scene">
                            <input type="hidden" name="act_id" value="<?php echo $act['id']; ?>">
                            <input type="text" name="title" placeholder="New scene title" required>
                            <input type="text" name="description" placeholder="Description">
                            <button type="submit">Add Scene</button>
                        </form>
                    </div>
                    <div class="meta">Act runtime: <?php echo format_runtime($actRuntime); ?></div>
                </div>
            </div>
        <?php endforeach; ?>

        <section class="section add-form">
            <h2>Add Act</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_act">
                <input type="text" name="title" placeholder="Act title" required>
                <button type="submit">Add Act</button>
            </form>
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-toggle]').forEach(function(header) {
            header.addEventListener('click', function() {
                var targetId = header.getAttribute('data-toggle');
                var body = document.getElementById(targetId);
                if (body) {
                    body.classList.toggle('hidden');
                }
            });
        });
    </script>
</body>

</html>