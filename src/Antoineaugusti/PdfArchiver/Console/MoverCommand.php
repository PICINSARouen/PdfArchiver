<?php namespace Antoineaugusti\PdfArchiver\Console;

use Antoineaugusti\PdfArchiver\Mover;
use League\Flysystem\AdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MoverCommand extends Command {

	/**
	 * The local adapter for Flysystem
	 *
	 * @var \League\Flysystem\AdapterInterface
	 */
	private $localAdapter = null;

	/**
	 * The remote adapter for Flysystem
	 *
	 * @var \League\Flysystem\AdapterInterface
	 */
	private $remoteAdapter = null;

	/**
	 * @var \Antoineaugusti\PdfArchiver\Mover
	 */
	private $mover;

	/**
	 * Set the local adapter
	 *
	 * @param \League\Flysystem\AdapterInterface $local
	 */
	public function setLocalAdapter(AdapterInterface $local)
	{
		$this->localAdapter = $local;
	}

	/**
	 * Set the remote adapter
	 *
	 * @param \League\Flysystem\AdapterInterface $remote
	 */
	public function setRemoteAdapter(AdapterInterface $remote)
	{
		$this->remoteAdapter = $remote;
	}

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
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->ensureAdaptersAreSet($output);

		$this->mover = new Mover($this->localAdapter, $this->remoteAdapter);

		$this->processDirectory($input, $output, $this->getStartPath($input));
	}

	/**
	 * Process recursively the given directory
	 *
	 * @param  \Symfony\Component\Console\Input\InputInterface $input
	 * @param  \Symfony\Component\Console\Output\OutputInterface $output
	 * @param  string $path The relative path to process
	 */
	private function processDirectory(InputInterface $input, OutputInterface $output, $path)
	{
		if ($this->mover->hasMakefile($path) AND $this->mover->hasPdfFolder($path))
		{
			$this->info($output, "Entering directory ".$path);

			// Call the makefile if necessary
			if ($input->getOption('make'))
				$this->mover->callMakefile($path);

			$this->processPDFDirectory($input, $output, $path.'/pdf');
		}

		// Process sub directories
		$directories = $this->mover->getDirectories($path);
		foreach ($directories as $directoryPath)
		{
			$this->processDirectory($input, $output, $directoryPath);
		}
	}

	/**
	 * Process a PDF directory at a given relative path
	 *
	 * @param  \Symfony\Component\Console\Input\InputInterface $input
	 * @param  \Symfony\Component\Console\Output\OutputInterface $output
	 * @param  string $path The relative path to process
	 */
	private function processPDFDirectory(InputInterface $input, OutputInterface $output, $path)
	{
		$files = $this->mover->getPDFFiles($path);

		foreach ($files as $file)
		{
			$filename = $this->getFilename($file);

			// If the remote has already the file,
			// ask before uploading again
			if ($this->mover->remoteHasContent($file))
			{
				$helper = $this->getHelper('question');
				$question = new ConfirmationQuestion('<error>Replace</error> <question>'.$filename."? (y/n)</question>", false);

				if ($helper->ask($input, $output, $question))
					$this->moveFile($output, $file);
			}
			else
				$this->moveFile($output, $file);

		}
	}

	private function moveFile($output, $file)
	{
		$filename = $this->getFilename($file);

		$this->info($output, "Uploading ".$filename);
		$this->mover->moveContentToRemote($file);
	}

	/**
	 * Get the filename of a content
	 *
	 * @param  array $content
	 * @return string
	 */
	private function getFilename($content)
	{
		$infos = pathinfo($content['path']);

		return $infos['basename'];
	}

	/**
	 * Make sure that the local and remote adapters are set
	 *
	 * @param  \Symfony\Component\Console\Output\OutputInterface $output
	 */
	private function ensureAdaptersAreSet($output)
	{
		if (is_null($this->localAdapter) OR is_null($this->remoteAdapter))
			return $this->erreur($output, "You need to set your local and remote adapters.");
	}

	/**
	 * Get the path to start from or provide a nice default value
	 *
	 * @param  \Symfony\Component\Console\Input\InputInterface $input
	 * @return string
	 */
	private function getStartPath($input)
	{
		$path = $input->getArgument('path');

		if (is_null($path))
			return '.';

		return $path;
	}

	/**
	 * Print an error message
	 *
	 * @param  \Symfony\Component\Console\Output\OutputInterface $output
	 * @param  string $message
	 */
	private function error($output, $message)
	{
		$output->writeln('<error>'.$message.'</error>');
	}

	/**
	 * Print an information message
	 *
	 * @param  \Symfony\Component\Console\Output\OutputInterface $output
	 * @param  string $message
	 */
	private function info($output, $message)
	{
		$output->writeln('<info>'.$message.'</info>');
	}
}