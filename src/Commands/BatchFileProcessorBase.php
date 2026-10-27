<?php

namespace HalloWelt\MediaWiki\Lib\CommandLineTools\Commands;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * work on a set of files
 *
 * Progress and error messages go through the PSR-3 logger given by setLogger(). Without one,
 * a Symfony ConsoleLogger on the command's output is used, so verbosity flags apply.
 */
abstract class BatchFileProcessorBase extends Command implements LoggerAwareInterface {
	use LoggerAwareTrait;

	/**
	 * @var Input\InputInterface
	 */
	protected $input = null;

	/**
	 * @var OutputInterface
	 */
	protected $output = null;

	/**
	 * @var string
	 */
	protected $src = '';

	/**
	 * @var string
	 */
	protected $dest = '';

	/**
	 * @var SplFileInfo[]
	 */
	protected $files = [];

	/**
	 * @var SplFileInfo
	 */
	protected $currentFile = null;

	/**
	 * @inheritDoc
	 */
	protected function configure() {
		$this
			->setDefinition( new Input\InputDefinition( [
				new Input\InputOption(
					'src',
					null,
					Input\InputOption::VALUE_REQUIRED,
					'Specifies the path to the input file or directory'
				),
				new Input\InputOption(
					'dest',
					null,
					Input\InputOption::VALUE_OPTIONAL,
					'Specifies the path to the output file or directory',
					'.'
				)
			] ) );

		return parent::configure();
	}

	/**
	 * @param Input\InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute( Input\InputInterface $input, OutputInterface $output ): int {
		$this->input = $input;
		$this->output = $output;
		$this->logger ??= new ConsoleLogger( $output );

		$this->src = realpath( $this->input->getOption( 'src' ) );
		if ( $this->src === false ) {
			$this->logger->error( 'Source does not exist: ' . $this->input->getOption( 'src' ) );
			return Command::FAILURE;
		}
		$this->dest = realpath( $this->input->getOption( 'dest' ) );
		if ( $this->dest === false ) {
			if ( !mkdir( $this->dest, 0777, true ) ) {
				$this->logger->error(
					'Destination does not exist and cannot be created: ' .
					$this->input->getOption( 'dest' ) );
				return Command::FAILURE;
			}
		} elseif ( !is_dir( $this->dest ) ) {
			$this->logger->error(
				'Destination is not a directory: ' .
				$this->input->getOption( 'dest' ) );
			return Command::FAILURE;
		}

		$this->logger->info( "Source: {$this->src}" );
		$this->logger->info( "Destination: {$this->dest}" );

		$this->makeFileList();
		$returnValue = $this->processFiles();

		$this->logger->info( 'Done.' );

		return $returnValue;
	}

	protected function makeFileList() {
		$this->files = [];

		// An input file was specified
		if ( is_file( $this->src ) ) {
			$this->files[$this->src] = new SplFileInfo( $this->src );
			return;
		}

		// An input directory was specified
		$this->logger->info( 'Fetching file list ...' );

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->src ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$extensionWhitelist = array_map( 'strtolower', $this->makeExtensionWhitelist() );
		foreach ( $files as $path => $file ) {
			if ( $file->isDir() ) {
				continue;
			}
			if ( !empty( $extensionWhitelist ) ) {
				$fileExt = strtolower( $file->getExtension() );
				if ( !in_array( $fileExt, $extensionWhitelist ) ) {
					continue;
				}
			}
			$this->files[$file->getPathname()] = new SplFileInfo( $file->getPathname() );
		}

		ksort( $this->files, SORT_NATURAL );
		$this->logger->info( 'Found {count} file(s).', [ 'count' => count( $this->files ) ] );
	}

	protected function processFiles(): int {
		$overallReturn = Command::SUCCESS;
		foreach ( $this->files as $file ) {
			$this->currentFile = $file;
			$result = $this->processFile( $file );
			if ( $result === false ) {
				$this->logger->error( "Failed to process file {$file->getPathname()}" );
				$overallReturn = Command::FAILURE;
			}
		}

		return $overallReturn;
	}

	/**
	 * @return array
	 */
	protected function makeExtensionWhitelist() {
		return [];
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	abstract protected function processFile( SplFileInfo $file ): bool;
}
