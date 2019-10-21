<?php

namespace HalloWelt\MediaWiki\Lib\CommandLineTools\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input;
use Symfony\Component\Console\Output\OutputInterface;
use SplFileObject;

abstract class BatchFileProcessorBase extends Command {

	/**
	 *
	 * @var Input\InputInterface
	 */
	protected $input = null;

	/**
	 *
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
	 *
	 * @var SplFileObject[]
	 */
	protected $files = [];

	/**
	 * @var SplFileObject
	 */
	protected $currentFile = null;

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

	protected function execute( Input\InputInterface $input, OutputInterface $output ) {
		$this->input = $input;
		$this->output = $output;

		$this->src = realpath( $this->input->getOption( 'src' ) );
		$this->dest = realpath( $this->input->getOption( 'dest' ) );

		$this->output->writeln( "Source: {$this->src}" );
		$this->output->writeln( "Destination: {$this->dest}\n" );

		$this->makeFileList();
		$this->processFiles();

		$this->output->writeln( '<info>Done.</info>' );
	}

	protected function makeFileList() {
		$this->files = [];

		//An input file was specified
		if( is_file( $this->src ) ) {
			$this->files[$this->src] = new SplFileObject( $this->src );
			return;
		}

		//An input directory was specified
		$this->output->write( 'Fetching file list ...' );

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->src ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$extensionWhitelist = array_map( 'strtolower', $this->makeExtensionWhitelist() );
		foreach ( $files as $path => $file ) {
			if( !empty( $extensionWhitelist ) ) {
				$fileExt = strtolower( $file->getExtension() );
				if( !in_array( $fileExt, $extensionWhitelist ) ) {
					continue;
				}
			}
			$this->files[$file->getPathname()] = new SplFileObject( $file->getPathname() );
		}

		ksort( $this->files, SORT_NATURAL );
		$this->output->writeln( '<info>done.</info>' );
	}

	protected function processFiles() {
		foreach( $this->files as $file ) {
			$this->currentFile = $file;
			$result = $this->processFile( $file );
		}
	}

	protected function makeExtensionWhitelist() {
		return [];
	}

	protected abstract function processFile( SplFileObject $file ) : bool;
}
