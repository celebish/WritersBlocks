<?php
$dbFile = __DIR__ . '/../writersblocks.db';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = file_get_contents(__DIR__ . '/schema.sql');
$pdo->exec($schema);

$pdo->exec('DELETE FROM users');
$pdo->exec('DELETE FROM projects');
$pdo->exec('DELETE FROM episodes');
$pdo->exec('DELETE FROM acts');
$pdo->exec('DELETE FROM scenes');
$pdo->exec('DELETE FROM clips');

$insertUser = $pdo->prepare('INSERT INTO users (email, password_hash) VALUES (:email, :password_hash)');
$insertUser->execute([
    ':email' => 'joshua.bridge@gmail.com',
    // Generated via password_hash("babyblue2", PASSWORD_DEFAULT)
    ':password_hash' => password_hash('babyblue2', PASSWORD_DEFAULT)
]);
$userId = (int)$pdo->lastInsertId();

$projects = [
    [
        'title' => 'Connections',
        'description' => 'Personal project',
        'is_public' => 0
    ],
    [
        'title' => 'The Greatest Story Ever Told',
        'description' => 'Open experimental project',
        'is_public' => 1
    ],
];

$insertProject = $pdo->prepare('INSERT INTO projects (title, description, is_public, owner_user_id, created_at) VALUES (:title, :description, :is_public, :owner_user_id, :created_at)');
$insertEpisode = $pdo->prepare('INSERT INTO episodes (project_id, title, description, created_at) VALUES (:project_id, :title, :description, :created_at)');
$insertAct = $pdo->prepare('INSERT INTO acts (episode_id, title, position) VALUES (:episode_id, :title, :position)');
$insertScene = $pdo->prepare('INSERT INTO scenes (act_id, title, description, position) VALUES (:act_id, :title, :description, :position)');
$insertClip = $pdo->prepare('INSERT INTO clips (scene_id, title, duration_seconds, sora_url, uploaded_url, position) VALUES (:scene_id, :title, :duration_seconds, :sora_url, :uploaded_url, :position)');

foreach ($projects as $projectIndex => $project) {
    $insertProject->execute([
        ':title' => $project['title'],
        ':description' => $project['description'],
        ':is_public' => $project['is_public'],
        ':owner_user_id' => $userId,
        ':created_at' => date('c'),
    ]);
    $projectId = (int)$pdo->lastInsertId();

    $insertEpisode->execute([
        ':project_id' => $projectId,
        ':title' => 'Pilot',
        ':description' => 'Initial episode for ' . $project['title'],
        ':created_at' => date('c'),
    ]);
    $episodeId = (int)$pdo->lastInsertId();

    $insertAct->execute([
        ':episode_id' => $episodeId,
        ':title' => 'Act 1',
        ':position' => 1,
    ]);
    $actId = (int)$pdo->lastInsertId();

    $insertScene->execute([
        ':act_id' => $actId,
        ':title' => 'Scene 1',
        ':description' => 'Opening scene',
        ':position' => 1,
    ]);
    $sceneId = (int)$pdo->lastInsertId();

    $insertClip->execute([
        ':scene_id' => $sceneId,
        ':title' => 'Clip 1',
        ':duration_seconds' => 10,
        ':sora_url' => null,
        ':uploaded_url' => null,
        ':position' => 1,
    ]);
    $insertClip->execute([
        ':scene_id' => $sceneId,
        ':title' => 'Clip 2',
        ':duration_seconds' => 15,
        ':sora_url' => null,
        ':uploaded_url' => null,
        ':position' => 2,
    ]);
}

echo "Database seeded to {$dbFile}\n";
