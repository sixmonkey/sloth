<?php

declare(strict_types=1);

namespace Sloth\Scaffolder;

use gossi\docblock\Docblock;
use League\CLImate\CLImate;
use Sloth\Facades\View;
use Sloth\Utility\Utility;
use Spatie\Emoji\Emoji;
use UnexpectedValueException;

/**
 * Scaffolder for creating new modules and related files.
 *
 * @since 1.0.0
 */
class Scaffolder {
	/**
	 * CLI climate instance.
	 *
	 * @since 1.0.0
	 * @var CLImate
	 */
	private CLImate $climate;

	/**
	 * Location of composer.json.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $composerConfigLocation;

	/**
	 * Parsed composer.json contents.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private array $composerConfig;

	/**
	 * Scaffolder constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->climate = new CLImate();
		$this->climate->addArt(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'Climate');
		$this->climate->green()->animation('logo')->speed(400)->enterFrom('left');

		$this->composerConfigLocation = self::getComposerJsonLocation();
		$this->composerConfig = json_decode(file_get_contents($this->composerConfigLocation . 'composer.json'), true);
	}

	/**
	 * Create a new module with all necessary files.
	 *
	 * @since 1.0.0
	 *
	 * @throws \Exception If scaffold creation fails
	 */
	public function createModule(): void {
		$context = [];
		$this->climate->green()->out('Creating new Module…');

		$context['name'] = null;
		$filenameModule = null;

		while ($context['name'] == null || file_exists($filenameModule)) {
			$input = $this->climate->green()->input("What will your Module be called?\r\n>");
			$context['name'] = trim($input->prompt());

			if ($context['name'] == null) {
				$this->climate->error(Emoji::pileOfPoo() . ' Please give a name for this Module!');
				continue;
			}

			$context['name'] = Utility::modulize($context['name']);
			$context['id'] = strtolower(Utility::normalize($context['name']));
			$context['name_view'] = Utility::viewize($context['name']);
			$context['name_acf'] = Utility::acfize($context['name']);

			$filenameModule = $GLOBALS['sloth']->container->get('path.theme') . DS . 'Module' . DS . $context['name'] . '.php';
			if (file_exists($filenameModule)) {
				$this->climate->error(Emoji::pileOfPoo() . ' ' . sprintf('A module called %s exists!', $context['name']));
			}
		}

		$input = $this->climate->green()->confirm('Do you want to use your module with Layotter?');
		$context['layotter'] = $input->confirmed() ? [] : false;

		if (is_array($context['layotter'])) {
			$input = $this->climate->green()->input("Please give a comprehensive title for your module.\r\n>");
			$context['layotter']['title'] = trim($input->prompt());

			$input = $this->climate->green()->input("Please give a short description of what this module will do.\r\n>");
			$context['layotter']['description'] = trim($input->prompt());

			$input = $this->climate->green()->input("Please choose an icon for this module.\r\n(choose from http://fontawesome.io/icons/)\r\n>");
			$context['layotter']['icon'] = trim($input->prompt());
		}

		$view = View::make('Scaffold.Module.Class');
		file_put_contents($filenameModule, $view->with($context)->render());

		$filenameView = $GLOBALS['sloth']->container->get('path.theme') . DS . 'View' . DS . 'Module' . DS . $context['name_view'] . '.twig';
		$view = View::make('Scaffold.Module.View');
		file_put_contents($filenameView, $view->with($context)->render());

		$sassDir = DIR_ROOT . DS . 'src' . DS . 'sass' . DS;
		if (!is_dir($sassDir)) {
			$sassDir = DIR_ROOT . DS . 'src' . DS . 'scss' . DS;
		}

		$filenameSass = $sassDir . 'modules' . DS . '_' . $context['name_view'] . '.scss';
		if (!is_dir(dirname($filenameSass))) {
			mkdir(dirname($filenameSass), 0777, true);
		}
		$view = View::make('Scaffold.Module.Sass');
		file_put_contents($filenameSass, $view->with($context)->render());

		$filenameSassBundle = $sassDir . 'bundle.scss';
		file_put_contents(
			$filenameSassBundle,
			sprintf("\n@import 'modules/%s';", $context['name_view']),
			FILE_APPEND
		);

		if ($context['layotter']) {
			$filenameAcf = $GLOBALS['sloth']->container->get('path.theme') . DS . 'acf-json' . DS . $context['name_acf'] . '.json';
			if (file_exists($filenameAcf)) {
				$this->climate->info(Emoji::thinkingFace() . ' ' . sprintf('A Field Group %s exists. Skipping scaffolding!', $context['name_acf']));
			} else {
				$view = View::make('Scaffold.Module.Acf');
				$data = json_decode($view->with($context + ['now' => time()])->render());
				if (json_last_error() !== JSON_ERROR_NONE) {
					throw new \Exception('Seems your scaffold is not correct json!');
				}
				file_put_contents($filenameAcf, json_encode($data));
			}
		}

		$this->climate->info(sprintf('Module %s created!', $context['name']));
	}

	/**
	 * Get the location of composer.json.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 * @throws UnexpectedValueException If composer.json cannot be located
	 */
	private static function getComposerJsonLocation(): string {
		$checkedPaths = [
			__DIR__ . '/../../../../../composer.json',
			__DIR__ . '/../../composer.json',
		];

		foreach ($checkedPaths as $path) {
			if (file_exists($path)) {
				return realpath(dirname($path)) . DIRECTORY_SEPARATOR;
			}
		}

		throw new UnexpectedValueException(sprintf(
			'PackageVersions could not locate your `composer.lock` location. This is assumed to be in %s.',
			json_encode($checkedPaths)
		));
	}

	/**
	 * Display help information.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function help(): void {
		$this->climate->green("Sloth CLI currently supports the following commands:\r\n");
		$r = new \ReflectionClass(__CLASS__);

		$methods = $r->getMethods(\ReflectionMethod::IS_PUBLIC);
		foreach ($methods as $method) {
			if (preg_match('/^_/', $method->name)) {
				continue;
			}
			$docblock = new Docblock($r->getMethod($method->name)->getDocComment());
			$this->climate->green()->bold($method->name)->tab()->green($docblock->getShortDescription());
		}
	}

	/**
	 * Get the WordPress installation directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function _getWordpressInstallDir(): string {
		return $this->composerConfigLocation . $this->composerConfig['extra']['wordpress-install-dir'] . DIRECTORY_SEPARATOR;
	}
}
