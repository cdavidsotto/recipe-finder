<?php
require __DIR__ . '/db.php';

$errors = [];
$name = trim($_POST['name'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$ingredient_text = trim($_POST['ingredients'] ?? '');
$saved_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // One ingredient per comma, stored in lower case without duplicates.
    $ingredients = [];
    foreach (explode(',', $ingredient_text) as $item) {
        $item = strtolower(trim($item));
        if ($item !== '' && !in_array($item, $ingredients, true)) {
            $ingredients[] = $item;
        }
    }

    if ($name === '' || strlen($name) > 100) {
        $errors[] = 'Please give the recipe a name (up to 100 characters).';
    }
    if ($instructions === '') {
        $errors[] = 'Please add some instructions.';
    }
    if (count($ingredients) === 0) {
        $errors[] = 'Please list at least one ingredient.';
    }
    foreach ($ingredients as $item) {
        if (strlen($item) > 50) {
            $errors[] = 'Each ingredient must be 50 characters or fewer.';
            break;
        }
    }

    if (count($errors) === 0) {
        // Save the recipe and its ingredients together, or not at all.
        $pdo->beginTransaction();

        $query = $pdo->prepare(
            "INSERT INTO recipes (name, instructions) VALUES (?, ?)"
        );
        $query->execute([$name, $instructions]);
        $saved_id = (int) $pdo->lastInsertId();

        $query = $pdo->prepare(
            "INSERT INTO recipe_ingredients (recipe_id, ingredient) VALUES (?, ?)"
        );
        foreach ($ingredients as $item) {
            $query->execute([$saved_id, $item]);
        }

        $pdo->commit();

        $name = '';
        $instructions = '';
        $ingredient_text = '';
    }
}

page_header('Add a recipe');
?>

<?php if ($saved_id !== null): ?>
    <p><strong>Recipe saved.</strong> It now appears in the ingredient search.</p>
<?php endif; ?>

<?php foreach ($errors as $error): ?>
    <p><strong><?= escape($error) ?></strong></p>
<?php endforeach; ?>

<form method="post" action="/add_recipe.php">
    <p>
        <label for="name">Recipe name:</label><br>
        <input type="text" name="name" id="name" maxlength="100"
            value="<?= escape($name) ?>">
    </p>
    <p>
        <label for="ingredients">Ingredients, separated by commas:</label><br>
        <input type="text" name="ingredients" id="ingredients" size="60"
            value="<?= escape($ingredient_text) ?>">
    </p>
    <p>
        <label for="instructions">Instructions:</label><br>
        <textarea name="instructions" id="instructions" rows="4"
            cols="60"><?= escape($instructions) ?></textarea>
    </p>
    <button type="submit">Save recipe</button>
</form>

<?php page_footer(); ?>
