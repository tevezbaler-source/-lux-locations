# Otelleri panele ve 11 dile tasir. Sayfa dosyalarina dokunmaz.
# Kullanim: powershell -ExecutionPolicy Bypass -File D:\Codex\lux-hotels-2026-11-04\oteller-panel\deploy-oteller.ps1
param(
  [string]$Src = $PSScriptRoot,
  [string]$FtpHost = "94.138.200.40",
  [string]$User = "luxlocationftp",
  [string]$Site = "https://luxlocationstours.com"
)
$ErrorActionPreference = "Stop"
$base = "ftp://$FtpHost/httpdocs"
$sec = Read-Host "FTP sifresi ($User)" -AsSecureString
$pass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
$cred = "$($User):$($pass)"
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
$bak = Join-Path $Src "canli-yedek-$stamp"
New-Item -ItemType Directory -Force -Path $bak | Out-Null

function Get-Remote([string]$remote, [string]$local) {
  # Dosya sunucuda yoksa curl hata verir; bu beklenen bir durum, betigi durdurmamali
  $old = $ErrorActionPreference; $ErrorActionPreference = "Continue"
  & curl.exe -s --user $cred "$base/$remote" -o $local 2>&1 | Out-Null
  $ok = ($LASTEXITCODE -eq 0 -and (Test-Path $local))
  $ErrorActionPreference = $old
  if (-not $ok -and (Test-Path $local)) { Remove-Item $local -Force }
  return $ok
}
function Up([string]$local, [string]$remote) {
  & curl.exe -s -S --ftp-create-dirs --user $cred -T $local "$base/$remote"
  if ($LASTEXITCODE -ne 0) { throw "Yuklenemedi: $remote" }
  Write-Host "  yuklendi: $remote"
}

# 1) Canlidaki dosyalarin yedegi
$files = @("lx-content/.htaccess", "lx-content/hotels.php", "lx-content/render.php", "admin/site/index.php")
foreach ($f in $files) { if (Get-Remote $f (Join-Path $bak ($f -replace "/", "_"))) { Write-Host "  yedeklendi: $f" } }

# 2) Otel verisi: panelde otel yoksa (dosya yok ya da bos) 19 otel yuklenir; varsa dokunulmaz
$hj = Join-Path $bak "hotels.json"
$uploadData = $true
if (Get-Remote "lx-content/data/hotels.json" $hj) {
  $t = ([IO.File]::ReadAllText($hj)).Trim()
  if ($t -ne "" -and $t -ne "[]") { $uploadData = $false; Write-Host "Panelde zaten otel var; otel verisine dokunulmadi (yedek: $hj)" -ForegroundColor Yellow }
}

# 3) Yeni dosyalar
foreach ($f in @("lx-content/hotels-i18n.php") + $files) { Up (Join-Path $Src ($f -replace "/", "\")) $f }
if ($uploadData) { Up (Join-Path $Src "hotels.json") "lx-content/data/hotels.json" }

# 4) Kontrol
function Code([string]$url) { return (& curl.exe -s -o NUL -w "%{http_code}" $url) }
Start-Sleep -Seconds 2
$checks = @("/oteller/", "/en/oteller/", "/de/oteller/", "/ar/oteller/marcello-hotel/", "/en/", "/admin/site/")
$bad = @()
foreach ($c in $checks) { $code = Code "$Site$c"; Write-Host "  $c -> $code"; if ($code -ne "200") { $bad += $c } }
$de = (& curl.exe -s "$Site/de/oteller/") -join "`n"
if ($de -notmatch "hotel-card") { $bad += "/de/oteller/ (otel karti yok)" }
$lib = Code "$Site/lx-content/hotels-i18n.php"
Write-Host "  hotels-i18n.php (403 olmali) -> $lib"
if ($bad.Count -gt 0) {
  Write-Host "Sorun var: $($bad -join ', '). Eski dosyalar geri yukleniyor..." -ForegroundColor Red
  foreach ($f in $files) { $l = Join-Path $bak ($f -replace "/", "_"); if (Test-Path $l) { Up $l $f } }
  if ($uploadData) { $e = Join-Path $bak "bos.json"; [IO.File]::WriteAllText($e, "[]"); Up $e "lx-content/data/hotels.json" }
  throw "Geri alindi. Bu ciktiyi Claude'a iletin."
}
Write-Host "TAMAM. Oteller artik panelde ve 11 dilde: $Site/de/oteller/" -ForegroundColor Green
