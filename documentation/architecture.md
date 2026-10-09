# Recipe Finder (Assignment 2) - architecture notes

## Components

| Component | Runs on | Role |
| --- | --- | --- |
| Proxy server | EC2 (Ubuntu, Apache reverse proxy) | Only public entry point; forwards HTTP to the app server |
| App server | EC2 (Ubuntu, Apache + PHP) | Recipe Finder pages; talks to RDS and Lambda |
| Database | Amazon RDS for MySQL | All application data (managed storage) |
| Shopping-list function | AWS Lambda (Python) | Combines recipe ingredients, removes duplicates, subtracts a pantry |

## Main data flow: generating a shopping list

1. User chooses recipes and one pantry, then names the list.
2. App server reads those recipes' ingredients and the pantry's ingredients from RDS.
3. App server sends them to Lambda as JSON (AWS CLI `aws lambda invoke`).
4. Lambda returns the ingredients still needed and which recipe needs each.
5. App server saves the result to RDS as a named snapshot and displays it.

## Trust boundaries

- Internet -> proxy: only HTTP (80) open to everyone; SSH (22) for administration.
- Proxy -> app: app accepts HTTP only from the proxy's security group.
- App -> RDS: port 3306 only from the app's security group; password supplied
  as a Terraform variable and never committed.
- App -> Lambda: app server uses the existing LabInstanceProfile (LabRole);
  AWS Academy does not allow creating IAM roles.

## Data model

- recipes (id, name, instructions)
- ingredients (id, name) - independent catalogue
- recipe_ingredients (recipe_id, ingredient_id)
- pantries (id, name) - shared, named
- pantry_ingredients (pantry_id, ingredient_id) - row present = ingredient present;
  deleting the row never deletes the catalogue ingredient or recipe links
- shopping_lists (id, name, pantry_id, created_at)
- shopping_list_recipes (list_id, recipe_id) - inputs kept for regeneration
- shopping_list_items (list_id, ingredient_name, needed_for) - copied text,
  so a saved list is a snapshot until explicitly regenerated

## Decisions (for the report)

1. Managed storage: RDS MySQL rather than MySQL on EC2 (not managed) or
   DynamoDB (data is relational; Assignment 1 queries reuse SQL).
2. Lambda receives data from PHP rather than reading RDS itself; the
   alternative needs Lambda VPC networking and a Python MySQL library.
3. Code delivery: app server's templatefile startup script (Lab 3 p. 59) runs
    git clone of the public repository, so the server runs the committed version;
    alternative was embedding the PHP in the startup script (16 KB user_data limit).
4. Shopping-list items stored as copied text rather than ingredient IDs.
5. Out of scope for now: quantities, unit conversion, user accounts, email,
   deleting recipes/ingredients.
