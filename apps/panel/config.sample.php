<?php
// Bu dosyayı config.php adıyla kopyalayın ve düzenleyin. config.php depoya girmez.
return [
    // PC'de deneme: 'sqlite' (ek kurulum gerekmez). Sunucuda: 'mysql'.
    'driver' => 'sqlite',
    'sqlite_path' => __DIR__ . '/data/panel.sqlite',

    // Sunucu (IHS cPanel) MySQL bilgileri
    'mysql_host' => 'localhost',
    'mysql_db'   => '',
    'mysql_user' => '',
    'mysql_pass' => '',

    // Eğitim bölümü her zaman ayrı bir SQLite dosyası kullanır; gerçek verilere dokunmaz.
    'training_path' => __DIR__ . '/data/egitim.sqlite',

    // Oturum süresi (dakika)
    'session_minutes' => 600,
];
