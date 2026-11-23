# Mobil uygulamanin siteden ihtiyac duydugu dosyalari luxlocationstours.com'a yukler:
#   lx-content/app-api.php  (uygulamanin canli icerik adresi)
#   gizlilik/index.html     (magazalarin istedigi gizlilik politikasi)
#   admin/db.php, admin/talep.php (formlardan gelen talepleri kaydeder)
#   admin/site/index.php (tek panel: Talepler + site duzenleme), admin/index.php (eski paneli buraya yonlendirir)
# Baska dosyaya dokunmaz. Dosyalar bu betikle ayni klasorde, sunucudaki yapiyla durmali.
$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$src = $PSScriptRoot
$hostName = 'luxlocationstours.com'
$files = @('lx-content/app-api.php', 'gizlilik/index.html', 'admin/db.php', 'admin/talep.php', 'admin/index.php', 'admin/site/index.php')

Write-Host ''
Write-Host '=== Lux uygulamasi: siteye dosyalar yukleniyor ===' -ForegroundColor Green
Write-Host 'Sifre sadece bu bilgisayardan sitenize gider, hicbir yere kaydedilmez.'
Write-Host ''
$user = Read-Host 'FTP kullanici adi (bos birakirsan luxlocationftp)'
if (-not $user) { $user = 'luxlocationftp' }
$sec  = Read-Host 'FTP sifresi' -AsSecureString
$pass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
$netrc = Join-Path $env:TEMP ('lxapp_' + [guid]::NewGuid().ToString('N') + '.txt')
Set-Content -ErrorAction Stop -Path $netrc -Value "machine $hostName login $user password $pass" -Encoding ASCII
$pass = $null
$tls = @('--ssl'); $base = "ftp://$hostName"

function Ftp([string[]]$a) {
  $out = & curl.exe -s -S --stderr - --netrc-file $netrc --connect-timeout 20 @tls @a
  return @{ code = $LASTEXITCODE; out = (($out | ForEach-Object { "$_" }) -join "`n") }
}
function Code([string]$url) { return (& curl.exe -s -o NUL -w "%{http_code}" $url) }

try {
  Write-Host 'Sunucuya baglaniliyor...'
  $tries = @(
    @{ base = "ftp://$hostName";        tls = @('--ssl') },
    @{ base = "ftp://$hostName";        tls = @('--ssl', '-k', '--ssl-no-revoke') },
    @{ base = "ftp://$hostName";        tls = @('--ssl-reqd', '-k', '--ssl-no-revoke', '--tlsv1.2', '--tls-max', '1.2') },
    @{ base = "ftp://$hostName";        tls = @('--ssl-reqd', '-k', '--ssl-no-revoke', '--tlsv1.0', '--tls-max', '1.2', '--ftp-pasv') },
    @{ base = "ftps://${hostName}:990"; tls = @('-k', '--ssl-no-revoke') },
    @{ base = "ftps://${hostName}:990"; tls = @('-k', '--ssl-no-revoke', '--tlsv1.2', '--tls-max', '1.2') }
  )
  $r = $null; $i = 0
  foreach ($t in $tries) {
    $i++; $tls = $t.tls; $base = $t.base
    Write-Host ("  Yontem {0}/{1}..." -f $i, $tries.Count) -ForegroundColor Gray
    $r = Ftp @('--list-only', "$base/")
    if ($r.code -eq 0) { break }
    if ($r.out -match '530|Login') { throw 'Kullanici adi veya sifre yanlis.' }
  }
  if ($r.code -ne 0) {
    Write-Host 'Sifreli baglantilarin hicbiri calismadi.' -ForegroundColor Yellow
    $ans = Read-Host 'Sifresiz FTP ile denensin mi? (e/h)'
    if ($ans -notmatch '^[eEyY]') { throw "Sunucuya baglanilamadi: $($r.out)" }
    $tls = @(); $base = "ftp://$hostName"
    $r = Ftp @('--list-only', "$base/")
    if ($r.code -ne 0) { throw "Sunucuya baglanilamadi: $($r.out)" }
  }
  $names = $r.out -split "`r?`n" | ForEach-Object { $_.Trim() }
  if ($names -contains 'httpdocs') { $root = 'httpdocs/' } elseif ($names -contains 'public_html') { $root = 'public_html/' } else { $root = '' }

  foreach ($f in $files) {
    $local = Join-Path $src ($f -replace '/', '\')
    if (-not (Test-Path $local)) { throw "Dosya yok: $local" }
    $r = Ftp @('--ftp-create-dirs', '-T', $local, "$base/$root$f")
    if ($r.code -ne 0) { throw "$f yuklenemedi: $($r.out)" }
    Write-Host "  yuklendi: $f"
  }

  Start-Sleep -Seconds 2
  $api = Code "https://$hostName/lx-content/app-api.php?lang=tr"
  $priv = Code "https://$hostName/gizlilik/"
  $mainPage = Code "https://$hostName/"
  $talep = Code "https://$hostName/admin/talep.php"
  Write-Host ''
  Write-Host "Kontrol: app-api $api  gizlilik $priv  ana sayfa $mainPage (200 olmali)  talep $talep (405 olmali)"
  if ($api -eq '200' -and $priv -eq '200' -and $talep -eq '405') { Write-Host 'TAMAM. Claude''a "yukledim" yazman yeterli.' -ForegroundColor Green }
  else { Write-Host 'Bir sorun var; bu ekranin goruntusunu Claude''a gonder.' -ForegroundColor Red }
}
catch { Write-Host ''; Write-Host ('HATA: ' + $_.Exception.Message) -ForegroundColor Red }
finally {
  Remove-Item $netrc -Force -ErrorAction SilentlyContinue
  Write-Host ''; Read-Host 'Kapatmak icin Enter'
}
