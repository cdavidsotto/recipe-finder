<?php
// Asks the AWS Lambda function to build a shopping list.
//
// The app server calls Lambda through the AWS CLI. The CLI gets temporary
// credentials from the instance's LabInstanceProfile (LabRole), so no AWS
// keys are stored on the server or in this repository.
//
// Input:  recipes = [['name' => ..., 'ingredients' => [...]], ...]
//         have    = ingredient names the user already has
// Output: [['ingredient' => ..., 'needed_for' => [recipe names]], ...]
//         or null if the call failed.

function build_shopping_list($config, $recipes, $have) {
    $payload = json_encode(['recipes' => $recipes, 'have' => $have]);

    $in_file = tempnam(sys_get_temp_dir(), 'list-in-');
    $out_file = tempnam(sys_get_temp_dir(), 'list-out-');
    file_put_contents($in_file, $payload);

    $command = 'HOME=/tmp aws lambda invoke'
        . ' --region ' . escapeshellarg($config['lambda']['region'])
        . ' --function-name ' . escapeshellarg($config['lambda']['function'])
        . ' --payload ' . escapeshellarg('fileb://' . $in_file)
        . ' ' . escapeshellarg($out_file)
        . ' 2>&1';

    exec($command, $output, $status);
    $result = json_decode((string) file_get_contents($out_file), true);

    unlink($in_file);
    unlink($out_file);

    if ($status !== 0 || !is_array($result) || !isset($result['items'])) {
        error_log('Lambda call failed: ' . implode(' ', $output));
        return null;
    }
    return $result['items'];
}

// Loads a list's saved inputs, asks Lambda for the result and replaces
// the saved items. Returns false if Lambda could not be reached.
function regenerate_list($pdo, $config, $list_id) {
    $query = $pdo->prepare(
        "SELECT r.id, r.name
         FROM shopping_list_recipes slr
         JOIN recipes r ON r.id = slr.recipe_id
         WHERE slr.list_id = ?
         ORDER BY r.name"
    );
    $query->execute([$list_id]);
    $recipe_rows = $query->fetchAll(PDO::FETCH_ASSOC);

    $query = $pdo->prepare(
        "SELECT ingredient FROM recipe_ingredients WHERE recipe_id = ?"
    );
    $recipes = [];
    foreach ($recipe_rows as $row) {
        $query->execute([$row['id']]);
        $recipes[] = [
            'name' => $row['name'],
            'ingredients' => $query->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    $query = $pdo->prepare(
        "SELECT ingredient FROM shopping_list_have WHERE list_id = ?"
    );
    $query->execute([$list_id]);
    $have = $query->fetchAll(PDO::FETCH_COLUMN);

    $items = build_shopping_list($config, $recipes, $have);
    if ($items === null) {
        return false;
    }

    // Replace the old snapshot with the new result.
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM shopping_list_items WHERE list_id = ?")
        ->execute([$list_id]);
    $insert = $pdo->prepare(
        "INSERT INTO shopping_list_items (list_id, ingredient, needed_for)
         VALUES (?, ?, ?)"
    );
    foreach ($items as $item) {
        $insert->execute([
            $list_id,
            $item['ingredient'],
            implode(', ', $item['needed_for']),
        ]);
    }
    $pdo->prepare(
        "UPDATE shopping_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    )->execute([$list_id]);
    $pdo->commit();

    return true;
}
