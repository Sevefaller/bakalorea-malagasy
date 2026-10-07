<?php
// Deterministic allowlist of project areas; never package runtime data or credentials.
$root = dirname(__DIR__);
$out = $root.'/release';
if (!is_dir($out)) mkdir($out,0755,true);
$zip = new ZipArchive();
if ($zip->open($out.'/bakalorea-aws.zip',ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Cannot create ZIP');
$skipDirs = ['vendor','node_modules','.git','.tools','.npm-cache','.runtime','artifacts','release','backups','dist','test-results','playwright-report'];
$files = [];
foreach (['backend','frontend','infra','deploy','docs','scripts'] as $folder) {
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root.'/'.$folder,FilesystemIterator::SKIP_DOTS),function ($item) use ($skipDirs) { return !$item->isLink() && (!$item->isDir() || !in_array($item->getFilename(),$skipDirs,true)); }));
    foreach ($it as $file) {
        if (!$file->isFile()) continue;
        $name = str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
        $base = basename($name);
        if (str_starts_with($base,'.env') && $base!=='.env.example') continue;
        if (preg_match('/\.(sqlite(?:-.*)?|log|pem|key|tsbuildinfo)$/i',$base) || str_contains($base,'phpunit.result.cache')) continue;
        if ((str_starts_with($name,'backend/storage/') || str_starts_with($name,'backend/bootstrap/cache/')) && $base!=='.gitignore') continue;
        $files[$name]=$file->getPathname();
    }
}
foreach (['compose.yaml','.dockerignore','.gitignore','.env.example','package.json','README.md'] as $name) $files[$name]=$root.'/'.$name;
ksort($files);
foreach ($files as $name=>$path) {
    $zip->addFile($path,$name);
    $zip->setExternalAttributesName($name,ZipArchive::OPSYS_UNIX,(str_ends_with($name,'.sh')?0100755:0100644)<<16);
}
$zip->close();
copy($root.'/deploy/aws/stack.yaml',$out.'/stack.yaml');
copy($root.'/deploy/aws/README.md',$out.'/LIRE-MOI-AWS.md');
$hash=hash_file('sha256',$out.'/bakalorea-aws.zip');
file_put_contents($out.'/bakalorea-aws.zip.sha256',$hash.'  bakalorea-aws.zip'.PHP_EOL);
echo count($files).' fichiers ; archive '.round(filesize($out.'/bakalorea-aws.zip')/1024/1024,2).' Mo'.PHP_EOL;
echo 'SHA256 '.$hash.PHP_EOL;
