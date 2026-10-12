# Automated check of the cloud deployment.
#
# Runs the main user workflow through the public web server (proxy):
#   1. the search page loads and finds a seeded recipe (app server -> RDS read);
#   2. a new recipe is saved and then found by search (RDS write + read);
#   3. a shopping list is built and saved (app server -> Lambda -> RDS).
# Any unexpected result stops the script with an error and a non-zero exit.
#
# Usage, from the repository root:
#   .\verify-cloud.ps1                      (reads the IP from Terraform)
#   .\verify-cloud.ps1 -WebIp 18.212.140.233
#
# Note: each run adds one test recipe and one test shopping list to RDS.

param(
    [string]$WebIp = ""
)

$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if ($WebIp -eq "") {
    $WebIp = (& terraform "-chdir=tf-deploy" output -raw web_server_ip)
    if ($LASTEXITCODE -ne 0 -or $WebIp -eq "") {
        throw "Could not read web_server_ip from Terraform; pass -WebIp."
    }
}

$base = "http://$WebIp/"
Write-Host "Checking $base"

function Get-Page {
    param([string]$Path)

    $response = Invoke-WebRequest `
        -Uri ($base + $Path) `
        -UseBasicParsing `
        -TimeoutSec 30

    if ($response.StatusCode -ne 200) {
        throw "Unexpected HTTP status for $Path"
    }
    return $response.Content
}

function Post-Form {
    param([string]$Path, [string]$Body)

    # Invoke-WebRequest follows the app's redirect to the result page.
    $response = Invoke-WebRequest `
        -Uri ($base + $Path) `
        -Method Post `
        -ContentType "application/x-www-form-urlencoded" `
        -Body $Body `
        -UseBasicParsing `
        -TimeoutSec 60

    if ($response.StatusCode -ne 200) {
        throw "Unexpected HTTP status for POST $Path"
    }
    return $response.Content
}

function Expect {
    param([string]$Content, [string]$Text, [string]$Label, [bool]$ShouldContain = $true)

    if ($Content.Contains($Text) -ne $ShouldContain) {
        throw "FAIL: $Label"
    }
    Write-Host "PASS: $Label"
}

# 1. Existing search through proxy -> app server -> RDS.
$page = Get-Page ""
Expect $page "Recipe Finder" "home page served through the proxy"

$page = Get-Page "?search=1&mode=and&ingredients%5B%5D=eggs&ingredients%5B%5D=butter"
Expect $page "<h3>Scrambled eggs</h3>" "can-make search reads seeded recipes from RDS"

# 2. Write a uniquely named recipe, then find it through search.
$stamp = Get-Date -Format "yyyyMMddHHmmss"
$ingredient = "checkitem$stamp"
$name = "Check recipe $stamp"

$body = "name=" + [uri]::EscapeDataString($name) +
    "&ingredients=" + [uri]::EscapeDataString("$ingredient, salt") +
    "&instructions=" + [uri]::EscapeDataString("Created by verify-cloud.ps1.")
$page = Post-Form "add_recipe.php" $body
Expect $page "Recipe saved." "new recipe written to RDS"

$page = Get-Page ("?search=1&ingredients%5B%5D=" + $ingredient)
Expect $page "<h3>$name</h3>" "new recipe read back from RDS"

# 3. Build a shopping list: Tomato pasta (2) + Lentil tomato soup (6),
#    already having oil and garlic.
$listName = "Check list $stamp"
$body = "name=" + [uri]::EscapeDataString($listName) +
    "&recipes%5B%5D=2&recipes%5B%5D=6&have%5B%5D=oil&have%5B%5D=garlic"
$page = Post-Form "lists.php" $body

Expect $page "Shopping list: $listName" "shopping list saved and reopened from RDS"
Expect $page "could not be built" "Lambda call succeeded" $false
Expect $page "<td>lentils</td>" "Lambda added lentils"
Expect $page "Lentil tomato soup, Tomato pasta" "Lambda combined tomatoes from both recipes"
Expect $page "<td>garlic</td>" "Lambda subtracted ingredients already owned" $false

Write-Host "All cloud workflow checks passed."
