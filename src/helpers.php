<?php
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function format_runtime(int $seconds): string
{
    $seconds = max(0, $seconds);
    $minutes = intdiv($seconds, 60);
    $remaining = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $remaining);
}

function calculate_scene_runtime(PDO $pdo, int $sceneId): int
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(duration_seconds), 0) as total FROM clips WHERE scene_id = :scene_id');
    $stmt->execute([':scene_id' => $sceneId]);
    $row = $stmt->fetch();
    return (int)$row['total'];
}

function calculate_act_runtime(PDO $pdo, int $actId): int
{
    $stmt = $pdo->prepare('SELECT id FROM scenes WHERE act_id = :act_id');
    $stmt->execute([':act_id' => $actId]);
    $total = 0;
    foreach ($stmt->fetchAll() as $scene) {
        $total += calculate_scene_runtime($pdo, (int)$scene['id']);
    }
    return $total;
}

function calculate_episode_runtime(PDO $pdo, int $episodeId): int
{
    $stmt = $pdo->prepare('SELECT id FROM acts WHERE episode_id = :episode_id');
    $stmt->execute([':episode_id' => $episodeId]);
    $total = 0;
    foreach ($stmt->fetchAll() as $act) {
        $total += calculate_act_runtime($pdo, (int)$act['id']);
    }
    return $total;
}
