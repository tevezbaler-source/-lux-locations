# Turkce /oteller/ sayfalarini da panele baglar.
# Codex'in /oteller/.htaccess dosyasi "RewriteEngine On" dedigi icin sitenin ana kurallari bu klasorde
# calismiyor ve eski sabit Turkce sayfalar gorunuyordu. Bu betik o dosyayi kural icermeyen bir dosyayla degistirir.
# Kullanim: powershell -ExecutionPolicy Bypass -File D:\Codex\lux-hotels-2026-11-04\oteller-panel\deploy-oteller-tr.ps1
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

# 1) Yedek
$old = Join-Path $bak "oteller_.htaccess"
if (Get-Remote "oteller/.htaccess" $old) { Write-Host "  yedeklendi: oteller/.htaccess" }

# 2) Kural icermeyen dosya (ana .htaccess kurallari bu klasorde de calisir)
$new = Join-Path $bak "oteller_.htaccess.yeni"
[IO.File]::WriteAllText($new, "# Oteller panelden (/admin/site/) sunulur; kurallar ana .htaccess dosyasinda.`n")
Up $new "oteller/.htaccess"

# 3) Kontrol
Start-Sleep -Seconds 2
$bad = @()
foreach ($c in @("/oteller/", "/oteller/marcello-hotel/", "/oteller/grand-marcello-hotel-spa/", "/en/oteller/", "/")) {
  $code = (& curl.exe -s -L -o NUL -w "%{http_code}" "$Site$c")
  Write-Host "  $c -> $code"
  if ($code -ne "200") { $bad += $c }
}
$tr = (& curl.exe -s "$Site/oteller/") -join "`n"
if ($tr -notmatch "data-tpl") { $bad += "/oteller/ (panel sayfasi gelmedi)" }
if ($bad.Count -gt 0) {
  Write-Host "Sorun var: $($bad -join ', '). Eski dosya geri yukleniyor..." -ForegroundColor Red
  if (Test-Path $old) { Up $old "oteller/.htaccess" }
  throw "Geri alindi. Bu ciktiyi Claude'a iletin."
}
Write-Host "TAMAM. Turkce otel sayfalari da artik panelden geliyor: $Site/oteller/" -ForegroundColor Green
