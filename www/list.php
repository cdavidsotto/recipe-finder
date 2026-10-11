<?php
require __DIR__ . '/db.php';
require __DIR__ . '/lambda.php';

$list_id = (int) ($_GET['id'] ?? 0);

$query = $pdo->prepare(
    "SELECT id, name, created_at, updated_at FROM shopping_lists WHERE id = ?"
);
$query->execute([$list_id]);
$list = $query->fetch(PDO::FETCH_ASSOC);

if ($list === false) {
    http_response_code(404);
    page_header('List not found');
    echo '<p><a href="/lists.php">Back to shopping lists</a></p>';
    page_footer();
    exit;
}

// Regenerate only when the user explicitly asks.
$failed = isset($_GET['failed']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = regenerate_list($pdo, $config, $list_id);
    header('Location: /list.php?id=' . $list_id . ($ok ? '' : '&failed=1'));
    exit;
}

$query = $pdo->prepare(
    "SELECT r.name
     FROM shopping_list_recipes slr
     JOIN recipes r ON r.id = slr.recipe_id
     WHERE slr.list_id = ?
     ORDER BY r.name"
);
$query->execute([$list_id]);
$recipe_names = $query->fetchAll(PDO::FETCH_COLUMN);

$query = $pdo->prepare(
    "SELECT ingredient FROM shopping_list_have WHERE list_id = ? ORDER BY ingredient"
);
$query->execute([$list_id]);
$have = $query->fetchAll(PDO::FETCH_COLUMN);

$query = $pdo->prepare(
    "SELECT ingredient, needed_for
     FROM shopping_list_items
     WHERE list_id = ?
     ORDER BY ingredient"
);
$query->execute([$list_id]);
$items = $query->fetchAll(PDO::FETCH_ASSOC);

page_header('Shopping list: ' . $list['name']);
?>

<?php if ($failed): ?>
    <p><strong>The shopping list could not be built just now.
    Your choices are saved; try Regenerate.</strong></p>
<?php endif; ?>

<p>
    Created <?= escape($list['created_at']) ?> UTC,
    last generated <?= escape($list['updated_at']) ?> UTC.
</p>

<p><strong>Recipes:</strong> <?= escape(implode(', ', $recipe_names)) ?></p>
<p><strong>Already have:</strong>
    <?= count($have) > 0 ? escape(implode(', ', $have)) : 'nothing ticked' ?></p>

<h2>To buy</h2>
<?php if (count($items) === 0): ?>
    <p>Nothing to buy.</p>
<?php else: ?>
    <table>
        <tr><th>Ingredient</th><th>Needed for</th></tr>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= escape($item['ingredient']) ?></td>
                <td><?= escape($item['needed_for']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<form method="post" action="/list.php?id=<?= (int) $list_id ?>">
    <p>
        <button type="submit">Regenerate</button>
        (uses the recipes' current ingredients)
    </p>
</form>

<p><a href="/lists.php">Back to shopping lists</a></p>

<?php page_footer(); ?>
