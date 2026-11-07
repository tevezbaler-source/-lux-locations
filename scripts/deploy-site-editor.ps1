# Site duzenleme ekranini (/admin/site/ + /lx-content/) canli siteye yukler.
# Sayfa dosyalarina dokunmaz. Canlidaki .htaccess yedeklenir, yeni kurallar sonuna eklenir;
# kontrol basarisiz olursa .htaccess otomatik eski haline doner.
# Kullanim: powershell -ExecutionPolicy Bypass -File D:\deploy-site-editor.ps1
# Dosyalar D:\lx-editor altinda, httpdocs ile ayni yapida olmali (lx-content\..., admin\site\..., htaccess-block.txt).
param(
  [string]$Src = "D:\lx-editor",
  [string]$FtpHost = "94.138.200.40",
  [string]$User = "luxlocationftp",
  [string]$Site = "https://luxlocationstours.com"
)
$ErrorActionPreference = "Stop"
$base = "ftp://$FtpHost/httpdocs"
$sec = Read-Host "FTP sifresi ($User)" -AsSecureString
$pass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
$cred = "$($User):$($pass)"

function Up([string]$local, [string]$remote) {
  & curl.exe -s -S --ftp-create-dirs --user $cred -T $local "$base/$remote"
  if ($LASTEXITCODE -ne 0) { throw "Yuklenemedi: $remote" }
  Write-Host "  yuklendi: $remote"
}

# 1) Canli .htaccess yedegi
$bak = Join-Path $Src ("htaccess-canli-yedek-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".txt")
& curl.exe -s -S --user $cred "$base/.htaccess" -o $bak
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $bak)) { throw "Canli .htaccess indirilemedi, islem durduruldu." }
Write-Host "Canli .htaccess yedeklendi: $bak"

# 2) Yeni dosyalar (.htaccess haric)
$files = @(
  "lx-content/.htaccess", "lx-content/lib.php", "lx-content/render.php",
  "lx-content/data/.htaccess", "lx-content/uploads/.htaccess",
  "admin/site/.htaccess", "admin/site/index.php"
)
foreach ($f in $files) { Up (Join-Path $Src ($f -replace "/", "\")) $f }

# 3) .htaccess: kurallar yoksa sonuna ekle
$live = [IO.File]::ReadAllText($bak)
if ($live -match "lx-content/render\.php") {
  Write-Host ".htaccess zaten guncel, dokunulmadi."
} else {
  $block = [IO.File]::ReadAllText((Join-Path $Src "htaccess-block.txt"))
  $new = $live.TrimEnd() + "`n`n" + $block
  $tmp = Join-Path $Src "htaccess-yeni.txt"
  [IO.File]::WriteAllText($tmp, $new, (New-Object Text.UTF8Encoding($false)))
  Up $tmp ".htaccess"
}

# 4) Kontrol
function Code([string]$url) {
  $c = & curl.exe -s -L -o NUL -w "%{http_code}" $url
  return $c
}
Start-Sleep -Seconds 2
$pageHtml = (& curl.exe -s -L "$Site/en/") -join "`n"
$ok = ($pageHtml -match "__next_f") -and ((Code "$Site/") -eq "200") -and ((Code "$Site/en/") -eq "200") -and ((Code "$Site/bogaz-tekne-turu/") -eq "200")
$ed = Code "$Site/admin/site/"
$lib = Code "$Site/lx-content/lib.php"
Write-Host "Ana sayfa: $ok  /admin/site/: $ed  lib.php (403 olmali): $lib"
if (-not $ok) {
  Write-Host "Site acilmadi! .htaccess eski haline donduruluyor..." -ForegroundColor Red
  Up $bak ".htaccess"
  throw "Geri alindi. Bu ciktiyi Claude'a iletin."
}
Write-Host "TAMAM. Simdi $Site/admin/site/ adresini acin." -ForegroundColor Green
