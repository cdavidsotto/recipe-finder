# Recipe Finder (COSC349 Assignment 2)

Recipe Finder helps you decide what to cook and what to buy. You can:

- search recipes by the ingredients you have ("any of these" or "can make with only these");
- add your own recipes, which are stored in a managed database;
- build a named **shopping list** from several recipes, minus the ingredients you already have,
  save it, reopen it later, and regenerate it after recipes change.

Assignment 1 ran the same app on three local Vagrant VMs. Assignment 2 moves it to AWS and
replaces the database VM with managed services (see "How it evolved" below).

## Architecture

```
Browser --HTTP 80--> web_server (EC2, Apache reverse proxy)
                        |  HTTP 80 to the app server's private IP
                        v
                     app_server (EC2, Apache + PHP, code cloned from this repository)
                        |-- MySQL 3306 --> RDS for MySQL "recipefinder-db" (private, no public access)
                        |-- aws lambda invoke (LabInstanceProfile / LabRole) --> Lambda "recipe-shopping-list"
```

| Component | Service | Role | Defined in |
| --- | --- | --- | --- |
| Web server | EC2 t2.micro, Ubuntu 24.04 | Public entry point; Apache `ProxyPass` to the app server | `tf-deploy/main.tf`, `tf-deploy/build-webserver-vm.tpl` |
| App server | EC2 t2.micro, Ubuntu 24.04 | PHP pages in `www/`; reads/writes RDS; calls Lambda | `tf-deploy/main.tf`, `tf-deploy/build-appserver-vm.tpl` |
| Database | Amazon RDS for MySQL, db.t3.micro, 20 GiB | All application data (recipes, shopping lists) | Created manually in the console (see Manual steps) |
| Shopping-list function | AWS Lambda, Python | Combines the chosen recipes' ingredients, removes duplicates, subtracts what the user already has | `lambda/lambda_function.py`, created manually in the console |

### Important data flows

1. **Search / add recipe:** browser -> web server -> app server -> SQL `SELECT`/`INSERT` on RDS.
2. **Shopping list:** the user picks recipes and ticks ingredients they already have.
   `www/lists.php` saves the choices in RDS (`shopping_lists`, `shopping_list_recipes`,
   `shopping_list_have`), then `www/lambda.php` reads each recipe's ingredients from RDS and sends
   them to Lambda as JSON with `aws lambda invoke`. Lambda returns
   `{"items": [{"ingredient": ..., "needed_for": [...]}]}`, which PHP saves in
   `shopping_list_items` as a snapshot (copied text). Reopening a list reads that snapshot;
   **Regenerate** asks Lambda again using the recipes' current ingredients.

### Trust boundaries

- **Internet -> web server:** ports 80/443 open to everyone (`allow_web`); port 22 for SSH (`allow_ssh`, key pair `vockey`).
- **Web server -> app server:** HTTP to the app server's private IP. *Known weakness:* the app server
  also uses `allow_web`, so it is reachable directly on port 80; a group-to-group rule
  (`allow_web` -> app server only) would close this.
- **App server -> RDS:** port 3306 via `allow_mysql`. RDS has **no public address**, so only resources inside the VPC
  can reach it. The app connects as the RDS `admin` user (*known weakness: not least privilege*).
  The password is supplied as a Terraform variable at apply time and written to
  `/etc/recipe-finder/config.ini` (owner root, group www-data, mode 640) on the app server; it is never in Git.
- **App server -> Lambda:** the app server has the existing Academy `LabInstanceProfile` (role `LabRole`) attached,
  so the AWS CLI obtains temporary credentials automatically; no AWS keys are stored on the server.
  AWS Academy does not allow creating IAM roles, so the existing role is used.

### Data model (RDS database `recipefinder`, see `setup-database.sql`)

| Table | Purpose |
| --- | --- |
| `recipes` | id, name, instructions |
| `recipe_ingredients` | recipe_id, ingredient |
| `shopping_lists` | id, name, created_at, updated_at |
| `shopping_list_recipes` | which recipes a list was built from (kept for regeneration) |
| `shopping_list_have` | ingredients the user already had |
| `shopping_list_items` | Lambda's result, stored as copied text so a saved list does not change until regenerated |

`setup-database.sql` is safe to run repeatedly (`CREATE TABLE IF NOT EXISTS`, seed rows use `ON DUPLICATE KEY UPDATE`).

## Repository layout

| Path | Contents |
| --- | --- |
| `tf-deploy/main.tf` | Terraform: security groups, both EC2 instances, variables, outputs |
| `tf-deploy/build-*.tpl` | Startup scripts (`user_data`) rendered by Terraform `templatefile` |
| `www/` | PHP application (`db.php` shared connection, `lambda.php` Lambda call) |
| `lambda/lambda_function.py` | Source of the Lambda function |
| `setup-database.sql` | Schema and seed recipes (run automatically by the app server's startup script) |
| `verify-cloud.ps1` | Automated check of the cloud workflow |
| `documentation/architecture.md` | Design notes |
| `Vagrantfile`, `build-*.sh`, `recipe-*.conf`, `verify.ps1` | Assignment 1 local deployment (kept for history) |

## Requirements

| Tool | Version used |
| --- | --- |
| Terraform | 1.16.5 (AWS provider 6.67.0, pinned by `tf-deploy/.terraform.lock.hcl`) |
| AWS CLI (local, optional) | 2.37.9 |
| PowerShell | Windows PowerShell 5.1 or later (for `verify-cloud.ps1`) |
| Git | any recent version |

Cloud: AWS Academy Learner Lab, region **us-east-1**, existing key pair **`vockey`**, existing
`LabInstanceProfile`/`LabRole`. Software on the servers (Apache, PHP, MySQL client, AWS CLI v2) is downloaded
by the startup scripts from Ubuntu's package archive and `https://awscli.amazonaws.com/`; it is not committed.

### Naming scheme

| Resource | Name |
| --- | --- |
| EC2 instances | `WebServer`, `AppServer` (Name tags) |
| Security groups | `allow_ssh`, `allow_web`, `allow_mysql` |
| RDS instance / database | `recipefinder-db` / `recipefinder` |
| Lambda function | `recipe-shopping-list` |

## Deploying

### Manual steps (and why)

1. **AWS credentials.** Start the Learner Lab and copy the AWS CLI credentials into `~/.aws/credentials`
   under a profile named `cosc349` (used by `provider "aws"` in `main.tf`). Academy credentials expire every few hours.
2. **RDS database** (console, about 10 minutes; once). Followed the AWS LAMP/RDS tutorial linked from Lab 3.
   RDS -> Create database -> Full configuration -> MySQL -> Free tier/Sandbox ->
   identifier `recipefinder-db`, master user `admin`, self-managed password -> `db.t3.micro`, 20 GiB, no autoscaling ->
   Don't connect to an EC2 resource, Default VPC, **Public access: No**, security group **`allow_mysql`**
   (create it first with `terraform apply`) -> Additional configuration: initial database name **`recipefinder`**,
   deletion protection off. Copy the endpoint into `variable "db_host"` in `main.tf`.
   *Why manual:* chosen to follow the course's RDS tutorial; the database is long-lived and is not recreated
   with the servers.
3. **Lambda function** (console, about 3 minutes; once), as in Lab 9: Create function -> Author from scratch ->
   name `recipe-shopping-list`, Python 3.12, execution role **existing `LabRole`** -> paste
   `lambda/lambda_function.py` into the Code tab -> Deploy.
   *Why manual:* follows Lab 9; Terraform packaging of Lambda code would need an extra provider not used in the course.
4. **Repository must be public** so the app server can `git clone` it at boot without storing a token.

### Commands

From `tf-deploy/`:

```powershell
terraform init
terraform plan        # prompts for db_password
terraform apply       # prompts for db_password, then type yes
```

Variables (`main.tf`): `repo_url` (public clone URL), `db_host` (RDS endpoint), `db_password`
(no default, sensitive, typed at plan/apply time).

Outputs: `web_server_ip` (open `http://<web_server_ip>/` - **http**, not https) and `app_server_ip` (for SSH).

**Expected deployment time** (measured 12 Oct 2026, replacing both servers): `terraform apply` about 2 minutes,
plus about 1 minute for the app server's startup script (cloud-init reported "Up 70.72 seconds") - about
**3 minutes** in total. A fresh deployment that also creates the security groups takes a similar time; RDS creation
(manual, once) took about 10 minutes.

### Verifying

From the repository root:

```powershell
.\verify-cloud.ps1 -WebIp <web_server_ip>
# or, if terraform is on PATH: .\verify-cloud.ps1
```

It checks, through the public web server: the search page and a seeded "can make" search (RDS read); saving a new
uniquely named recipe and finding it by search (RDS write + read); and building a shopping list from two recipes,
checking Lambda's combined and subtracted result is saved and reopened. Any failure stops with `FAIL:` and an error.
Each run adds one test recipe and one test shopping list.

Troubleshooting on the app server: `sudo tail /var/log/cloud-init-output.log` (startup script) and
`sudo tail /var/log/apache2/recipe-error.log` (PHP errors, including `Lambda call failed: ...`).

### Redeploying

- **Change to PHP only:** commit and push, then on the app server `sudo git -C /opt/recipe-finder pull`.
- **Change to a startup script (`.tpl`):** `user_data` only runs at first boot, so recreate the server:
  `terraform taint aws_instance.app_server` (and/or `web_server`), then `terraform apply`.
  Recreating the app server also recreates the web server's configuration, because its proxy address is the app
  server's new private IP.
- **Learner Lab restarts:** stopped instances keep their disks, private IPs and data, but get **new public IPs**;
  use `terraform plan` (shows the drift) or the EC2 console to find them. Start RDS from the console if it is stopped.

### Stopping and removing

| Action | Effect on data |
| --- | --- |
| End Lab / stop instances | Servers and RDS stop; everything is preserved. |
| `terraform destroy` | Deletes both EC2 instances and the three security groups (`allow_mysql` cannot be deleted while RDS uses it). **RDS data is untouched** because RDS is not managed by Terraform. |
| Delete RDS (console) | Deletes all recipes and shopping lists (optionally take a final snapshot first). |
| Delete Lambda (console) | Shopping lists can no longer be built; saved lists remain readable. |

## How it evolved from Assignment 1

| Assignment 1 (Vagrant) | Assignment 2 (AWS) |
| --- | --- |
| `webserver` VM, Apache proxy to `192.168.56.12` | `web_server` EC2, same proxy config but the app server's private IP is filled in by Terraform |
| `appserver` VM serving `/vagrant/www` | `app_server` EC2 serving `/opt/recipe-finder/www` cloned from this repository |
| `dbserver` VM running MySQL, read-only user | RDS for MySQL (managed storage); the app now **writes** recipes and shopping lists |
| - | Lambda shopping-list function (new feature) |
| `vagrant up`, `verify.ps1` | `terraform apply`, `verify-cloud.ps1` |

## Sources and reuse

- COSC349 Lab 3 (Terraform EC2, security groups, `templatefile`, `taint`), Lab 5 (multi-VM, line endings),
  Lab 9 (Lambda with LabRole); Lectures 11, 12, 14.
- Additional documentation: Terraform AWS provider docs (`variable`, `iam_instance_profile`), AWS RDS console and
  LAMP tutorial, AWS CLI installation and `lambda invoke` docs.
- **AI use:** Claude (Anthropic) drafted the PHP pages in `www/`, the schema changes in `setup-database.sql`,
  `verify-cloud.ps1` and this README, and guided the Terraform/console steps, which I typed and ran myself.
  I checked the output by running the deployment, the browser workflow and `verify-cloud.ps1`, and fixed problems
  found during testing (see the report).
