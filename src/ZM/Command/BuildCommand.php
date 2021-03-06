<?php


namespace ZM\Command;

use League\CLImate\CLImate;
use Phar;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ZM\Console\TermColor;
use ZM\Utils\DataProvider;

class BuildCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'build';
    /**
     * @var OutputInterface
     */
    private $output = null;

    protected function configure() {
        $this->setDescription("Build an \".phar\" file | 将项目构建一个phar包");
        $this->setHelp("此功能将会把整个项目打包为phar");
        $this->addOption("target", "D", InputOption::VALUE_REQUIRED, "Output Directory | 指定输出目录");
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $this->output = $output;
        $target_dir = $input->getOption("target") ?? (WORKING_DIR);
        if (mb_strpos($target_dir, "../")) $target_dir = realpath($target_dir);
        if ($target_dir === false) {
            $output->writeln(TermColor::color8(31) . zm_internal_errcode("E00039") . "Error: No such file or directory (" . $target_dir . ")" . TermColor::RESET);
            return 1;
        }
        $output->writeln("Target: " . $target_dir);
        if (mb_substr($target_dir, -1, 1) !== '/') $target_dir .= "/";
        if (ini_get('phar.readonly') == 1) {
            $output->writeln(TermColor::color8(31) . zm_internal_errcode("E00040") . "You need to set \"phar.readonly\" to \"Off\"!");
            $output->writeln(TermColor::color8(31) . "See: https://stackoverflow.com/questions/34667606/cant-enable-phar-writing");
            return 1;
        }
        if (!is_dir($target_dir)) {
            $output->writeln(TermColor::color8(31) . zm_internal_errcode("E00039") . "Error: No such file or directory ($target_dir)" . TermColor::RESET);
            return 1;
        }
        $filename = "server.phar";
        $this->build($target_dir, $filename);

        return 0;
    }

    private function build($target_dir, $filename) {
        @unlink($target_dir . $filename);
        $phar = new Phar($target_dir . $filename);
        $phar->startBuffering();
        $climate = new CLImate();

        $all = DataProvider::scanDirFiles(DataProvider::getSourceRootDir(), true, true);

        $all = array_filter($all, function ($x) {
            $dirs = preg_match("/(^(bin|config|resources|src|vendor)\/|^(composer\.json|README\.md)$)/", $x);
            return !($dirs !== 1);
        });

        sort($all);
        $progress = $climate->progress()->total(count($all));

        $archive_dir = DataProvider::getSourceRootDir();
        foreach ($all as $k => $v) {
            $phar->addFile($archive_dir . "/" . $v, $v);
            $progress->current($k + 1, "Adding " . $v);
        }

        $phar->setStub(
            "#!/usr/bin/env php\n" .
            $phar->createDefaultStub(LOAD_MODE == 0 ? "src/entry.php" : "vendor/zhamao/framework/src/entry.php")
        );
        $phar->stopBuffering();
        $this->output->writeln("Successfully built. Location: " . $target_dir . "$filename");
    }
}
