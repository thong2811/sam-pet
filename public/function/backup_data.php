<?php
$sourceFolder = __DIR__ . '/../../data';
$backupFolder = __DIR__ . '/../../data/backup';

$logger = \Application\Service\CommonService::logger();

if (!is_dir($backupFolder)) {
    mkdir($backupFolder, 0777, true);
}

$backupFileName = 'backup_data_' . date('Y-m-d') . '.zip';
$backupFilePath = $backupFolder . DIRECTORY_SEPARATOR . $backupFileName;
if (file_exists($backupFilePath)) {
    return;
}

// Xóa các file backup cũ hơn 90 ngày
$backupFiles = glob($backupFolder . '/backup_data_*.zip');
$now = time();

foreach ($backupFiles as $file) {
    if (is_file($file) && ($now - filemtime($file)) > (90 * 24 * 60 * 60)) {
        unlink($file);
        $logger->info("Đã xóa file backup: " . $file);
    }
}

// Tạo file zip
$zip = new ZipArchive();
if ($zip->open($backupFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
    $logger->info('Bắt đầu backup dữ liệu.');

    // Lấy tất cả các file trong thư mục nguồn
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceFolder),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $fileName = $file->getFilename();

            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'csv') {
                $zip->addFile($filePath, $fileName);
            }
        }
    }

    $zip->close();
    $logger->info("Backup thành công: $backupFileName");
} else {
    $logger->error('Backup thất bại.');
}
