# Lux Locations: SEO guncellemesini luxlocationstours.com'a yukler (2026-11-24).
#  - Sayfalar (11 dil), gorseller, _next dosyalari, robots.txt, kvkk/, gizlilik/
#  - lx-content/*.php (site haritasi, sayac, oteller)
#  - admin/site/istatistik.php (ziyaret istatistigi) ve admin/site/index.php (Talepler menusu cikti, Istatistik eklendi)
#  - Canli .htaccess yedeklenir, basina "lux-seo" blogu eklenir (eski blok varsa degistirilir).
#    Kontrol basarisiz olursa .htaccess otomatik eski haline doner.
# Dokunmaz: admin/ (admin/site/index.php ve istatistik.php haric), lx-content/data, lx-content/uploads, videolar.
# Dosyalar bu betikle ayni klasorde: site\ (derlenmis site, apps/web/out) ve htaccess-seo.txt
$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$src = $PSScriptRoot
$site = Join-Path $src 'site'
$hostName = 'luxlocationstours.com'
$utf8 = New-Object Text.UTF8Encoding $false

Write-Host ''
Write-Host '=== Lux Locations: SEO guncellemesi siteye yukleniyor ===' -ForegroundColor Green
Write-Host 'Sifre sadece bu bilgisayardan sitenize gider, hicbir yere kaydedilmez.'
Write-Host ''
if (-not (Test-Path (Join-Path $site 'en\index.html'))) { Write-Host "Derlenmis site bulunamadi: $site" -ForegroundColor Red; Read-Host 'Kapatmak icin Enter'; exit 1 }

# Yuklenecek dosyalar (sira: gorseller ve kod once, sayfalar sonra)
$all = Get-ChildItem -Path $site -Recurse -File | ForEach-Object { $_.FullName.Substring($site.Length + 1).Replace('\', '/') }
$files = $all | Where-Object {
  $_ -notmatch '(^|/)\.htaccess$' -and
  $_ -notmatch '^admin/' -and
  $_ -notmatch '^lx-content/(data|uploads)/' -and
  $_ -notmatch '\.mp4$'
}
$files += 'admin/site/istatistik.php'
$files += 'admin/site/index.php'   # site duzenleyici: Talepler menusu kaldirildi (otel panelinde), Istatistik baglantisi eklendi
$rank = { param($f) if ($f -match '^(assets|_next)/') { 0 } elseif ($f -match '^lx-content/') { 1 } elseif ($f -match '^admin/') { 2 } else { 3 } }
$files = $files | Sort-Object { & $rank $_ }, { $_ }
Write-Host ("Yuklenecek dosya: {0}" -f $files.Count)

$user = Read-Host 'FTP kullanici adi (bos birakirsan luxlocationftp)'
if (-not $user) { $user = 'luxlocationftp' }
$sec  = Read-Host 'FTP sifresi' -AsSecureString
$pass = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec))
$netrc = Join-Path $env:TEMP ('lxseo_' + [guid]::NewGuid().ToString('N') + '.txt')
Set-Content -ErrorAction Stop -Path $netrc -Value "machine $hostName login $user password $pass" -Encoding ASCII
$pass = $null
$tls = @('--ssl'); $base = "ftp://$hostName"

function Ftp([string[]]$a) {
  $out = & curl.exe -s -S --stderr - --netrc-file $netrc --connect-timeout 20 @tls @a
  return @{ code = $LASTEXITCODE; out = (($out | ForEach-Object { "$_" }) -join "`n") }
}
function Code([string]$url) { return (& curl.exe -s -o NUL -w "%{http_code}" $url) }
function CodeL([string]$url) { return (& curl.exe -s -L -o NUL -w "%{http_code}" $url) }

$htBackup = $null
try {
  Write-Host 'Sunucuya baglaniliyor...'
  $tries = @(
    @{ base = "ftp://$hostName";        tls = @('--ssl') },
    @{ base = "ftp://$hostName";        tls = @('--ssl', '-k', '--ssl-no-revoke') },
    @{ base = "ftp://$hostName";        tls = @('--ssl-reqd', '-k', '--ssl-no-revoke', '--tlsv1.2', '--tls-max', '1.2') },
    @{ base = "ftps://${hostName}:990"; tls = @('-k', '--ssl-no-revoke') }
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

  # 1) Canli .htaccess yedegi
  $htBackup = Join-Path $src ('htaccess-canli-yedek-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.txt')
  $r = Ftp @('-o', $htBackup, "$base/$root.htaccess")
  if ($r.code -ne 0 -or -not (Test-Path $htBackup)) { $htBackup = $null; throw "Canli .htaccess indirilemedi, islem durduruldu. $($r.out)" }
  Write-Host "Canli .htaccess yedeklendi: $htBackup"

  # 2) Dosyalar
  $n = 0; $failed = @()
  foreach ($f in $files) {
    $n++
    $local = Join-Path $site ($f -replace '/', '\')
    if (-not (Test-Path $local)) { $failed += $f; continue }
    $r = Ftp @('--ftp-create-dirs', '-T', $local, "$base/$root$f")
    if ($r.code -ne 0) { $r = Ftp @('--ftp-create-dirs', '-T', $local, "$base/$root$f") }
    if ($r.code -ne 0) { $failed += $f }
    if ($n % 25 -eq 0) { Write-Host ("  {0}/{1} yuklendi" -f $n, $files.Count) }
  }
  if ($failed.Count -gt 0) { Write-Host ("Yuklenemeyen {0} dosya: {1}" -f $failed.Count, ($failed -join ', ')) -ForegroundColor Yellow }
  else { Write-Host ("  {0} dosyanin hepsi yuklendi." -f $files.Count) -ForegroundColor Green }

  # 3) .htaccess: lux-seo blogu basa (eskisi varsa cikarilir)
  $live = [IO.File]::ReadAllText($htBackup, $utf8)
  if ($live.Length -gt 0 -and [int][char]$live[0] -eq 0xFEFF) { $live = $live.Substring(1) }
  $live = [regex]::Replace($live, '(?s)# BEGIN lux-seo.*?# END lux-seo\r?\n?', '')
  $block = [IO.File]::ReadAllText((Join-Path $src 'htaccess-seo.txt'), $utf8)
  if ($block.Length -gt 0 -and [int][char]$block[0] -eq 0xFEFF) { $block = $block.Substring(1) }
  $newHt = Join-Path $src 'htaccess-yeni.txt'
  [IO.File]::WriteAllText($newHt, ($block.TrimEnd() + "`n`n" + $live.TrimStart()), $utf8)
  $r = Ftp @('-T', $newHt, "$base/$root.htaccess")
  if ($r.code -ne 0) { throw ".htaccess yuklenemedi: $($r.out)" }
  Write-Host '.htaccess guncellendi.'

  # 4) Kontrol
  Start-Sleep -Seconds 2
  $c = [ordered]@{
    'ana sayfa (/ -> /en/)' = (CodeL "https://$hostName/")
    '/en/'                  = (Code "https://$hostName/en/")
    'Turkce ana sayfa'      = (Code "https://$hostName/index.html")
    'robots.txt'            = (Code "https://$hostName/robots.txt")
    'sitemap.xml'           = (Code "https://$hostName/sitemap.xml")
    'oteller'               = (Code "https://$hostName/oteller/")
    'kvkk'                  = (Code "https://$hostName/kvkk/")
    'eski hakkimizda.html'  = (Code "https://$hostName/hakkimizda.html")
  }
  $c.GetEnumerator() | ForEach-Object { Write-Host ("  {0,-24} {1}" -f $_.Key, $_.Value) }
  $sm = (& curl.exe -s "https://$hostName/sitemap.xml") -join "`n"
  $ok = $c['/en/'] -eq '200' -and $c['ana sayfa (/ -> /en/)'] -eq '200' -and $c['Turkce ana sayfa'] -eq '200' -and $c['oteller'] -eq '200' -and $c['robots.txt'] -eq '200' -and $sm -match '<urlset'
  if (-not $ok) {
    Write-Host 'Kontrol basarisiz: .htaccess eski haline donduruluyor...' -ForegroundColor Yellow
    $r = Ftp @('-T', $htBackup, "$base/$root.htaccess")
    if ($r.code -eq 0) { Write-Host '.htaccess eski haline dondu. Bu ekranin goruntusunu Claude''a gonder.' -ForegroundColor Red }
    else { Write-Host ".htaccess geri yuklenemedi! Yedek: $htBackup  Claude'a hemen haber ver." -ForegroundColor Red }
  } else {
    Write-Host ''
    Write-Host 'TAMAM. Claude''a "yukledim" yazman yeterli.' -ForegroundColor Green
  }
}
catch { Write-Host ''; Write-Host ('HATA: ' + $_.Exception.Message) -ForegroundColor Red; if ($htBackup) { Write-Host "Canli .htaccess yedegi: $htBackup" } }
finally {
  Remove-Item $netrc -Force -ErrorAction SilentlyContinue
  Write-Host ''; Read-Host 'Kapatmak icin Enter'
}
