<?php

namespace Antoineaugusti\PdfArchiver\Console;

use Antoineaugusti\PdfArchiver\Mover;
use League\Flysystem\AdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MoverCommand extends Command
{
    /**
     * The local adapter for Flysystem.
     *
     * @var AdapterInterface
     */
    private $localAdapter = null;

    /**
     * The remote adapter for Flysystem.
     *
     * @var AdapterInterface
     */
    private $remoteAdapter = null;

    /**
     * @var Mover
     */
    private $mover;

    /**
     * Set the local adapter.
     *
     * @param AdapterInterface $local
     */
    public function setLocalAdapter(AdapterInterface $local)
    {
        $this->localAdapter = $local;
    }

    /**
     * Set the remote adapter.
     *
     * @param AdapterInterface $remote
     */
    public function setRemoteAdapter(AdapterInterface $remote)
    {
        $this->remoteAdapter = $remote;
    }

    /**
     * Parameters for the command.
     */
    protected function configure()
    {
        $this
            ->setName('archive')
            ->setDescription('Archive PDF files')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'The path to start from.'
            )
            ->addOption(
               'make',
               null,
               InputOption::VALUE_NONE,
               'If set, we will call the makefile when we find one.'
            )
            ->addOption(
               'force',
               null,
               InputOption::VALUE_NONE,
               'If set, replace existing PDF files on the remote.'
            )
            ->addOption(
               'last',
               null,
               InputOption::VALUE_NONE,
               'If set, only try to upload the last generated PDF file in each directory.'
            )
        ;
    }

    /**
     * Execute the command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->ensureAdaptersAreSet($output);

        $this->mover = new Mover($this->localAdapter, $this->remoteAdapter);

        $this->processDirectory($input, $output, $this->getStartPath($input));
    }

    /**
     * Process recursively the given directory.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $path   The relative path to process
     */
    private function processDirectory(InputInterface $input, OutputInterface $output, $path)
    {
        if ($this->mover->hasMakefile($path) and $this->mover->hasPdfFolder($path)) {
            $this->info($output, 'Entering directory '.$path);

            // Call the makefile if necessary
            if ($input->getOption('make')) {
                $this->mover->callMakefile($path);
            }

            $this->processPDFDirectory($input, $output, $path.'/pdf');
        }

        // Process sub directories
        $directories = $this->mover->getDirectories($path);
        foreach ($directories as $directoryPath) {
            $this->processDirectory($input, $output, $directoryPath);
        }
    }

    /**
     * Process a PDF directory at a given relative path.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $path   The relative path to process
     */
    private function processPDFDirectory(InputInterface $input, OutputInterface $output, $path)
    {
        $files = $this->mover->getPDFFiles($path);

        // If we've been asked to upload only the last generated file
        // Try to process only this one
        if ($input->getOption('last')) {
            $this->processFile($input, $output, end($files));

        // Otherwise, loop over each files
        } else {
            foreach ($files as $file) {
                $this->processFile($input, $output, $file);
            }
        }
    }

    /**
     * Determine if we need to copy a file to the remote filesystem,
     * and if so, do it.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param array           $file   The file to process
     */
    private function processFile(InputInterface $input, OutputInterface $output, $file)
    {
        // If the remote has already the file,
        // ask before uploading again
        if ($this->mover->remoteHasContent($file)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion($this->buildQuestionForFile($file), false);

            if ($input->getOption('force') or $helper->ask($input, $output, $question)) {
                $this->moveFile($output, $file);
            }
        } else {
            $this->moveFile($output, $file);
        }
    }

    /**
     * Build the question string for a file.
     *
     * @param string $file
     */
    private function buildQuestionForFile($file)
    {
        $filename = $this->getFilename($file);

        return '<error>Replace</error> <question>'.$filename.'? [y/N]</question>';
    }

    /**
     * Copy a file from the local filesystem to the remote one.
     *
     * @param OutputInterface $output
     * @param string          $file
     */
    private function moveFile(OutputInterface $output, $file)
    {
        $filename = $this->getFilename($file);

        $this->info($output, 'Uploading '.$filename);
        $this->mover->moveContentToRemote($file);
    }

    /**
     * Get the filename of a content.
     *
     * @param array $content
     *
     * @return string
     */
    private function getFilename($content)
    {
        $infos = pathinfo($content['path']);

        return $infos['basename'];
    }

    /**
     * Make sure that the local and remote adapters are set.
     *
     * @param OutputInterface $output
     */
    private function ensureAdaptersAreSet(OutputInterface $output)
    {
        if (is_null($this->localAdapter) or is_null($this->remoteAdapter)) {
            return $this->erreur($output, 'You need to set your local and remote adapters.');
        }
    }

    /**
     * Get the path to start from or provide a nice default value.
     *
     * @param InputInterface $input
     *
     * @return string
     */
    private function getStartPath(InputInterface $input)
    {
        $path = $input->getArgument('path');

        if (is_null($path)) {
            return '.';
        }

        return $path;
    }

    /**
     * Print an error message.
     *
     * @param OutputInterface $output
     * @param string          $message
     */
    private function error(OutputInterface $output, $message)
    {
        $output->writeln('<error>'.$message.'</error>');
    }

    /**
     * Print an information message.
     *
     * @param OutputInterface $output
     * @param string          $message
     */
    private function info(OutputInterface $output, $message)
    {
        $output->writeln('<info>'.$message.'</info>');
    }
}
