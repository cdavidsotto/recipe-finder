# AWS Lambda function "recipe-shopping-list" (Python 3.12, execution role LabRole).
# Deployed manually through the Lambda console (Code tab -> Deploy), as in Lab 9.
#
# Input:  {"recipes": [{"name": ..., "ingredients": [...]}, ...], "have": [...]}
# Output: {"items": [{"ingredient": ..., "needed_for": [recipe names]}, ...]}


def lambda_handler(event, context):
    # Ingredients the user already has are not added to the list.
    have = set(item.strip().lower() for item in event.get('have', []))

    # Map each needed ingredient to the recipes that use it.
    needed = {}
    for recipe in event.get('recipes', []):
        for ingredient in recipe.get('ingredients', []):
            name = ingredient.strip().lower()
            if name == '' or name in have:
                continue
            needed.setdefault(name, [])
            if recipe['name'] not in needed[name]:
                needed[name].append(recipe['name'])

    items = [
        {'ingredient': name, 'needed_for': needed[name]}
        for name in sorted(needed)
    ]
    return {'items': items}
