<?php
/**
 * Kerisy Framework
 *
 * PHP Version 7
 *
 * @author          kaihui.wang <hpuwang@gmail.com>
 * @copyright      (c) 2015 putao.com, Inc.
 * @package         kerisy/framework
 * @version         3.0.0
 */

namespace Kerisy\Foundation\Command\Artisan;

use Kerisy\Console\Input\InputArgument;
use Kerisy\Console\Input\InputInterface;
use Kerisy\Console\Output\OutputInterface;
use Kerisy\Console\Input\InputOption;
use Kerisy\Foundation\Command\Base;
use Kerisy\Foundation\Storage\Pdo;
use Kerisy\Support\Dir;
use Kerisy\Support\Log;

class Dbsync extends Base
{
    private $tableName = null;

    protected function configure()
    {
        //InputArgument
        $this->setName('dbsync')
            ->addOption('--config', '-c', InputOption::VALUE_OPTIONAL, 'sync database config')
            ->addOption('--sqldir', '-d', InputOption::VALUE_OPTIONAL, 'sql file dir path')
            ->addOption('--prefix', '-p', InputOption::VALUE_OPTIONAL, 'database table prefix')
            ->setDescription('database sync project');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storageConfig = config()->get("storage.server.pdo");
        $inputConfig = $input->getOption("config");
        $inputConfig = $inputConfig?$inputConfig:$storageConfig;

        $sqlpath = $input->getOption("sqldir");
        $sqlpath = $sqlpath?$sqlpath:APPLICATION_PATH."/sql/";

        $prefix = $input->getOption("prefix");
        $prefix = $prefix?$prefix:"";

        $newPrefix = $inputConfig['prefix'];
        $this->tableName = "{$newPrefix}dbsync";

        //判断表格是否存在
        $db = new Pdo($inputConfig);
        $sql =  "SHOW TABLES like '{$this->tableName}'";
        $checkData = $db->fetch($sql);

        if(!$checkData){
            $sql = "CREATE TABLE `{$this->tableName}` ( `id` INT NOT NULL AUTO_INCREMENT , `filename` VARCHAR(100) NOT NULL DEFAULT '', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci";
            $db->exec($sql);
            Log::sysinfo("dbSync initialize success!");
        }

        $this->importDb($inputConfig, $sqlpath, $prefix);

        Log::sysinfo("sync completed!");
    }

    protected function getImportFilePath($sqlpath, $db, $newPrefix)
    {
        $importFileNames = $db->getField("filename", [], true, "", "", "", "", "",'dbsync');

        $this->getFiles($sqlpath, $files);

        if(!$files) return ;

        $newfile = [];
        foreach ($files as $v){
            $newfile[] = substr(str_replace($sqlpath, "", $v),0,-4);
        }

        $diffFile = array_diff($newfile, $importFileNames);

        if(!$diffFile) return ;

        asort($diffFile);

        return $diffFile;
    }

    protected function getFiles($sqlpath, &$files)
    {
        $handle = dir($sqlpath);
        $source = Dir::formatPath($sqlpath);
        while ($entry = $handle->read()) {
            if (($entry != ".") && ($entry != "..")) {
                if (is_dir($source . $entry)) {
                    $this->getFiles($sqlpath, $files);
                } else {
                    if(pathinfo($entry, PATHINFO_EXTENSION) == 'sql') $files[] = $source . $entry;
                }
            }
        }
    }

    protected function importDb($inputConfig, $sqlpath, $prefix)
    {
        $db = new Pdo($inputConfig);

        $newPrefix = $inputConfig['prefix'];
        $prefix = $prefix?$prefix:$newPrefix;

        $files = $this->getImportFilePath($sqlpath, $db, $newPrefix);
        if(!$files){
            Log::sysinfo("no sql need import");
            return ;
        }
        foreach ($files as $v){
            $filePath = $sqlpath.$v.".sql";
            Log::sysinfo("start import :". $filePath);
            $db->import($filePath, $prefix, $newPrefix);
            $insertData = [];
            $insertData['filename'] = $v;
            $insertData['created_at'] = date('Y-m-d H:i:s');
            $insertData['updated_at'] = date('Y-m-d H:i:s');
            $db->insert($insertData, "dbsync");
        }
    }


}