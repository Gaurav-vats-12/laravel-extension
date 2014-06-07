<?php namespace Jumilla\Laravel\Commands;

use Illuminate\Console\Command;

abstract class AbstractCommand extends Command {

	protected function makeDirectories($subPaths)
	{
		foreach ($subPaths as $path) {
			$this->files->makeDirectory($this->basePath.'/'.$path);
		}
	}

	protected function makeComposerJson($namespace, $subPaths)
	{
		$data = [
			'autoload' => [
				'psr-4' => [$namespace.'\\' => $subPaths],
			],
		];
		$this->files->prepend($this->basePath.'/composer.json', json_encode($data, JSON_PRETTY_PRINT));
	}

	protected function makePhpConfig($path, $data)
	{
		$this->files->prepend($this->basePath.'/'.$path, "<?php\n\nreturn ".var_export($data, true).";\n");
	}

	protected function makePhpSource($path, $source, $namespace = null)
	{
		if ($namespace) {
			$namespace = "namespace {$namespace};";
		}
		$this->files->prepend($this->basePath.'/'.$path, "<?php {$namespace}\n\n{$source}\n");
	}

	protected function makeTextFile($path, $text)
	{
		$this->files->prepend($this->basePath.'/'.$path, "{$text}\n");
	}

}
