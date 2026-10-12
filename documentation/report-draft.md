# COSC349 Assignment 2 Report - Recipe Finder in the cloud

Christian Sotto - repository: https://github.com/cdavidsotto/recipe-finder - commit: `<FILL IN>`

## 1. What the application does and how it was reached

Recipe Finder lets a user search recipes by ingredients they have, add new recipes, and build named shopping
lists: the user chooses recipes and ticks ingredients they already have, and the app saves a de-duplicated list
of what to buy (with which recipe needs each item). Saved lists can be reopened and regenerated after recipes
change.

For the recording the app was reached at `http://<web_server_ip>/` (the public IP of the web server EC2
instance, output by Terraform). Deployed and working at submission. EC2 and RDS are stopped automatically 
when the Learner Lab session ends and can be restarted from the console; terraform apply recreates the servers 
in about 3 minutes.

## 2. Architecture

```
Browser -> web_server (EC2, Apache proxy) -> app_server (EC2, PHP) -> RDS for MySQL
                                                  \-> Lambda (shopping-list calculation)
```

**Compute approach (option 1 of the brief).** Two interacting EC2 instances: the web server is the only intended
public entry point and forwards every request to the app server's private IP (Apache `ProxyPass`); the app server
runs the PHP application. This keeps the Assignment 1 web/app split, but in Assignment 2 the proxy's target is
filled in by Terraform (`templatefile` with `aws_instance.app_server.private_ip`, Lab 3 p. 58-59) because AWS
assigns private IPs. I chose this rather than a Lambda-only design because the existing PHP application could be
reused and the course examples for Lambda did not include a web front end (API Gateway or function URLs).

**Managed storage: Amazon RDS for MySQL.** All application data lives in RDS (`recipes`, `recipe_ingredients`,
`shopping_lists`, `shopping_list_recipes`, `shopping_list_have`, `shopping_list_items`). The application performs
meaningful writes (new recipes; new and regenerated shopping lists) and reads (search, reopening lists). The data
is relational (recipes have many ingredients; lists have many recipes and items), and the Assignment 1 SQL queries
could be reused unchanged.

**Further managed service: AWS Lambda.** The function `recipe-shopping-list` receives the chosen recipes'
ingredients and the user's "already have" list as JSON, combines duplicates, subtracts owned ingredients and
returns each needed ingredient with the recipes that use it. The PHP app saves this result in RDS. Lambda is
stateless (Lecture 14): everything it needs is in the request, and persistence stays in RDS.

**Identity.** The app server uses the Academy's existing `LabInstanceProfile`/`LabRole`, so the AWS CLI on the
server obtains temporary credentials to invoke Lambda; no AWS keys are stored on the server (Lecture 11 p. 13:
"attach workload identity, not credential files"). Creating a narrower role was not possible in the Learner Lab.

## 3. Two architecture decisions and the alternatives considered

**Decision 1 - RDS rather than MySQL on an EC2 VM (or DynamoDB).**
Alternative A: keep Assignment 1's database VM on EC2. This would be IaaS only: I would have to install, patch,
back up and secure MySQL myself, and the brief requires a managed storage service. Alternative B: DynamoDB. It is
managed and cheap at low use, but the data is relational and the existing queries (e.g. "can make" uses `NOT
EXISTS` across recipe ingredients) would need redesigning. RDS keeps SQL, gives automated backups, and lets the
database live independently of the servers: rebuilding both EC2 instances did not affect any saved data.
Trade-off: RDS costs money every hour it runs (Section 6), unlike DynamoDB's per-request pricing.

**Decision 2 - Lambda receives data from PHP instead of reading RDS itself.**
Alternative: let Lambda query RDS directly. That would require placing the function inside the VPC, adding a
Python MySQL library to the deployment package, and giving Lambda database credentials - none of which are covered
in the labs, and it would add a second place holding the password. Instead PHP reads the needed rows and sends
them as JSON; Lambda performs pure computation. Trade-off: Lambda is not independently aware of the data, and
very large lists would mean larger payloads, which is acceptable for this application.

Other decisions recorded in the repository: shopping-list results are stored as copied text (a snapshot) rather
than ingredient IDs; the app code is delivered by `git clone` of the public repository in the startup script
rather than embedding PHP in `user_data` (16 KB limit); the app server port 80 is open to all (`allow_web`), a
known weakness - a security-group-to-security-group rule (Lecture 11 p. 11) would restrict it to the proxy.

## 4. Deployment, reproducibility and testing

Terraform (`tf-deploy/main.tf`) creates the three security groups and both EC2 instances and configures them with
startup scripts (`build-webserver-vm.tpl`, `build-appserver-vm.tpl`) that install Apache/PHP/AWS CLI, clone the
repository, write the database configuration, load `setup-database.sql` into RDS and configure the proxy. The
database password is a sensitive Terraform variable typed at apply time and never committed; state files are
ignored by Git.

Manual steps, each justified in the README: Learner Lab credentials; creating RDS in the console (following the
AWS tutorial linked from Lab 3); creating the Lambda function in the console (as in Lab 9); making the repository
public so servers can clone it without a stored token.

Reproducibility check: on 12 Oct 2026 I tainted and recreated both servers. `terraform apply` took about 2 minutes
(13:05:01-13:07:05) and the app server's cloud-init finished 70.7 s after boot, so the app was usable about 3
minutes after starting. Saved recipes and shopping lists survived because they are in RDS.

Automated check: `verify-cloud.ps1` drives the real workflow through the public web server: a seeded "can make"
search, saving a uniquely named recipe and finding it again (RDS write and read), and building a shopping list from
two recipes, asserting that Lambda combined tomatoes from both recipes and subtracted garlic. All 9 checks passed
against the rebuilt servers; pointing it at a wrong address fails with an error.

## 5. A debugging problem I investigated

**Symptom.** After adding the Lambda function, building a shopping list in the browser always showed "The shopping
list could not be built just now". The list itself was saved (recipes and owned ingredients appeared), so RDS was
working and only the Lambda step failed.

**Evidence.** Running `aws lambda invoke` by hand on the app server as the `ubuntu` user succeeded (StatusCode
200), so the function and LabRole permissions were fine. `lambda.php` logs failures with `error_log`, so I read
`/var/log/apache2/recipe-error.log`:
`cannot create snap home dir: mkdir /var/www/snap: permission denied ... home directories outside of /home needs
configuration`.

**Cause.** I had installed the AWS CLI with `snap`. Apache runs PHP as `www-data`, whose home directory is
`/var/www`; snap applications refuse to run for users whose home is outside `/home`. My manual test passed only
because `ubuntu`'s home is `/home/ubuntu`. Re-running the command with `sudo -u www-data` reproduced the failure,
confirming the cause. (An earlier attempt to fix it by using the full path `/snap/bin/aws` did not help.)

**Fix.** Install AWS CLI v2 with AWS's own installer (`/usr/local/bin/aws`, no home-directory restriction), update
`lambda.php` and the startup template, and verify by rebuilding the app server from scratch and running
`verify-cloud.ps1`. Lesson: test as the same user and environment the service actually runs under.

Smaller problems: the RDS instance was created before the `allow_mysql` security group existed, so it received the
VPC `default` group and the app server could not have connected; I attached `allow_mysql` via Modify. A Terraform
state backup file was accidentally committed and was removed from history before submission.

## 6. Cost and resource lifecycle

Prices for us-east-1, on-demand, checked 12 Oct 2026 against the AWS Pricing Calculator (calculator.aws). Learner Lab
accounts have no free tier; usage is drawn from the US$50 lab credit. A month is taken as 730 hours.

| Resource | Unit price | Entirely idle (running, no users) | Lightly used |
| --- | --- | --- | --- |
| 2 x EC2 t2.micro | $0.0116/h each | 2 x 730 x 0.0116 = $16.94 | $16.94 |
| 2 x public IPv4 (in use) | $0.005/h each | 2 x 730 x 0.005 = $7.30 | $7.30 |
| 2 x 8 GiB EBS gp3 (server disks) | $0.08/GB-month | 16 x 0.08 = $1.28 | $1.28 |
| RDS db.t3.micro, Single-AZ | $0.017/h | 730 x 0.017 = $12.41 | $12.41 |
| RDS 20 GiB General Purpose storage | ~$0.115/GB-month | 20 x 0.115 = $2.30 | $2.30 |
| RDS automated backups | free up to the provisioned storage size | $0 | $0 |
| Lambda | $0.20 per 1M requests + $0.0000166667/GB-s | $0 (no requests) | 6,000 lists x 0.1 s x 0.125 GB = 75 GB-s -> about $0.002 |
| Data transfer out | small pages; first 100 GB/month free | $0 | $0 |
| **Total per month** | | **about $40.2** | **about $40.2** |

Usage assumptions for "lightly used": about 200 shopping lists a day (6,000 per month), each a 0.1-second Lambda run
at 128 MB, and a few hundred page views a day. At this scale cost is almost entirely the always-on resources
(two EC2 instances, their public IPs and RDS); Lambda is negligible, which is why moving more logic into Lambda (or
using DynamoDB) would be the main way to reduce idle cost.

**Resources that cost money while idle:** both EC2 instances and their public IPv4 addresses while running, EBS
volumes (even when instances are stopped), RDS instance hours while running, and RDS storage (even when stopped).
When the Learner Lab session ends, instances and RDS stop: the remaining cost is storage only, about
$1.28 (EBS) + $2.30 (RDS) = **about $3.6 per month**. (AWS restarts a stopped RDS instance automatically after 7 days.)

**Stopping or removing, and what is preserved:**
- *Stop (End Lab, or stop in the console):* everything is preserved; public IPs change on restart.
- *`terraform destroy`:* deletes both servers and their disks and the security groups; nothing of value is lost
  because the servers hold no data - all recipes and lists stay in RDS. (`allow_mysql` cannot be deleted while RDS
  uses it.) `terraform apply` recreates the servers in about 3 minutes.
- *Delete the RDS instance (console):* permanently deletes all application data unless a final snapshot is taken.
- *Delete the Lambda function:* lists can no longer be built or regenerated; saved lists remain readable.

## 7. Recording

Recording URL: `https://youtu.be/7rrnpt-25jE`. Timeline:
0:01 = Added a recipe page.
0:10 = Recipe Saved, write to the RDS (mysql)
0:13 = Searched for an ingredient within the recipe (banana), results (read from the RDS)
0:23 = Shopping list creation, including the new recipe we made, as well as a pre-existing one.
0:37 = Result of the lambda function, saved in the RDS. Any subtracted ingredients were the ones we 'Already Had'
0:42 = Saved lists appear in the Shopping Lists Page
0:54 = App Server Terminal
1:19 = EC2, RDS and Lambda Consoles.

## 8. AI-use statement

I used Claude (Anthropic) throughout. It explained the course labs and lectures,
quizzed me on each step, and guided the Terraform, security-group, RDS and Lambda work, which I typed and ran myself.
It drafted the PHP pages in `www/`, the schema changes in `setup-database.sql`, `verify-cloud.ps1`, the README and a
draft of this report. I checked its output by deploying it, testing the workflow in the browser and with the
automated check, reading the server logs when things failed, and editing the documentation. No credentials or
private keys were shared with the AI. 

The website layout, and any additional features of the application from the first assignment, were coded primarily by Claude.

## 9. Sources

- COSC349 Lab 3 (EC2, Terraform, `templatefile`, `taint`), Lab 5 (multi-VM, line endings), Lab 9 (Lambda with
  LabRole); Lectures 8, 11, 12, 14, 20.
- Terraform AWS provider documentation (`variable`, `iam_instance_profile`); AWS RDS console and LAMP/RDS tutorial;
  AWS CLI v2 installation and `lambda invoke` documentation.
- Pricing (accessed 12 Oct 2026): AWS Lambda pricing (aws.amazon.com/lambda/pricing); Amazon EBS pricing
  (aws.amazon.com/ebs/pricing); AWS public IPv4 charge (AWS News Blog, "New - AWS Public IPv4 Address Charge");
  Amazon RDS for MySQL pricing (aws.amazon.com/rds/mysql/pricing); EC2 and RDS instance rates cross-checked with
  calculator.holori.com, and confirmed in the AWS Pricing Calculator (calculator.aws), accessed 12 Oct 2026.
