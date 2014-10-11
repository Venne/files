<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\DI;

use Nette\Application\Routers\Route;
use Nette\DI\Statement;
use Venne\System\DI\SystemExtension;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FilesExtension extends \Nette\DI\CompilerExtension
	implements
	\Kdyby\Doctrine\DI\IEntityProvider,
	\Venne\System\DI\IPresenterProvider,
	\Kdyby\Translation\DI\ITranslationProvider,
	\Venne\System\DI\IJsProvider,
	\Venne\System\DI\ICssProvider
{

	/** @var string[] */
	public $defaults = array(
		'ajaxUploadDir' => '%publicDir%/ajaxFileUpload',
		'publicDir' => '%publicDir%/media',
		'protectedDir' => '%dataDir%/media',
	);

	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$this->compiler->parseServices(
			$container,
			$this->loadFromFile(__DIR__ . '/services.neon')
		);
		$config = $this->getConfig($this->defaults);

		$container->addDefinition($this->prefix('ajaxFileUploaderFactory'))
			->setImplement('Venne\Files\IAjaxFileUploaderControlFactory')
			->setArguments(array(
				$container->expand('%publicDir%/ajaxFileUpload'),
				$container->expand('%publicDir%')
			))
			->setInject(true);

		$container->getDefinition('nette.latteFactory')
			->addSetup('Venne\Files\Macros\MediaMacro::install(?->getCompiler())', array('@self'));

		$container->addDefinition($this->prefix('fileListener'))
			->setClass('Venne\Files\Listeners\FileListener', array(
				$container->expand($config['publicDir']),
				$container->expand($config['protectedDir']),
				$container->expand($container->parameters['wwwDir'])
			));

		$container->addDefinition($this->prefix('cacheCleanerListener'))
			->setClass('Venne\Files\Listeners\CacheCleanerListener', array(
				$container->expand($config['publicDir']),
			));

		$container->addDefinition($this->prefix('fileBrowserControlFactory'))
			->setImplement('Venne\Files\FileBrowser\IFileBrowserControlFactory')
			->setInject(true);

		$container->addDefinition($this->prefix('defaultPresenter'))
			->setClass('Venne\Files\AdminModule\DefaultPresenter')
			->addTag(SystemExtension::TAG_ADMINISTRATION, array(
				'link' => 'Admin:Files:Default:',
				'category' => 'Content',
				'name' => 'Manage files',
				'description' => 'Manage files and directories',
				'priority' => 110,
			));

		$container->addDefinition($this->prefix('filePresenter'))
			->setClass('Venne\Files\AdminModule\FilePresenter');

		$router = $container->getDefinition('router');
		$router->addSetup('$service[] = new Nette\Application\Routers\Route(?, ?);', array(
			'public/media/_cache/<size>/<format>/<type>/<url .+>',
			array(
				'presenter' => 'Admin:Files:File',
				'action' => 'image',
				'url' => array(
					Route::VALUE => '',
					Route::FILTER_IN => null,
					Route::FILTER_OUT => null,
				)
			)
		));
		$router->addSetup('$service[] = new Nette\Application\Routers\Route(?, ?);', array(
			'public/media/<url .+>',
			array(
				'presenter' => 'Admin:Files:File',
				'action' => 'default',
				'url' => array(
					Route::VALUE => '',
					Route::FILTER_IN => null,
					Route::FILTER_OUT => null,
				)
			)
		));

		$container->addDefinition($this->prefix('browserControlFactory'))
			->setImplement('Venne\Files\SideComponents\IBrowserControlFactory')
			->setArguments(array(null, null))
			->setInject(true);

		$container->addDefinition($this->prefix('filesControlFactory'))
			->setImplement('Venne\Files\SideComponents\IFilesControlFactory')
			->setInject(true)
			->addTag(SystemExtension::TAG_SIDE_COMPONENT, array(
				'name' => 'Files',
				'description' => 'Files',
				'args' => array(
					'icon' => 'fa fa-folder-open',
				),
			));
	}

	/**
	 * @return string[]
	 */
	public function getEntityMappings()
	{
		return array(
			'Venne\Files' => dirname(__DIR__) . '/*.php',
		);
	}

	/**
	 * @return string[]
	 */
	public function getPresenterMapping()
	{
		return array(
			'Admin:Files' => 'Venne\*\AdminModule\*Presenter',
		);
	}

	/**
	 * @return string[]
	 */
	public function getTranslationResources()
	{
		return array(
			__DIR__ . '/../../../Resources/lang',
		);
	}

	/**
	 * @return string[]
	 */
	public function getCssFiles()
	{
		return array(
			'@venne.files/css/fileBrowser.control.css',
			'@venne.files/vendor/fancytree/skin-vista/ui.fancytree.css',
		);
	}

	/**
	 * @return string[]
	 */
	public function getJsFiles()
	{
		return array(
			'@venne.files/vendor/fancytree/jquery.fancytree-custom.min.js',
			'@venne.files/vendor/blueimp-file-upload/jquery.iframe-transport.js',
			'@venne.files/vendor/blueimp-file-upload/jquery.fileupload.js',
		);
	}

}
