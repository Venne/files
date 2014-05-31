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

use Kdyby\Doctrine\DI\IEntityProvider;
use Kdyby\Translation\DI\ITranslationProvider;
use Nette\Application\Routers\Route;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\DI\Statement;
use Nette\PhpGenerator\PhpLiteral;
use Venne\System\DI\IJsProvider;
use Venne\System\DI\IPresenterProvider;
use Venne\System\DI\SystemExtension;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FilesExtension extends CompilerExtension implements IEntityProvider, IPresenterProvider, ITranslationProvider, IJsProvider
{

	/** @var array */
	public $defaults = array(
		'ajaxUploadDir' => '%publicDir%/ajaxFileUpload',
		'publicDir' => '%publicDir%/media',
		'protectedDir' => '%dataDir%/media',
	);


	/**
	 * Processes configuration data. Intended to be overridden by descendant.
	 * @return void
	 */
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$container->addDefinition($this->prefix('ajaxFileUploaderFactory'))
			->setImplement('Venne\Files\IAjaxFileUploaderControlFactory')
			->setArguments(array(
				$container->expand('%publicDir%/ajaxFileUpload'),
				$container->expand('%publicDir%')
			))
			->setInject(TRUE);

		$container->getDefinition('nette.latteFactory')
			->addSetup('Venne\Files\Macros\MediaMacro::install(?->getCompiler())', array('@self'));

		$container->addDefinition($this->prefix('fileFormFactory'))
			->setClass('Venne\Files\FileFormFactory', array(new Statement('@system.admin.basicFormFactory')));

		$container->addDefinition($this->prefix('fileEditFormFactory'))
			->setClass('Venne\Files\FileEditFormFactory', array(new Statement('@system.admin.ajaxFormFactory')));

		$container->addDefinition($this->prefix('dirFormFactory'))
			->setClass('Venne\Files\DirFormFactory', array(new Statement('@system.admin.ajaxFormFactory')));

		$container->addDefinition($this->prefix('fileListener'))
			->setClass('Venne\Files\Listeners\FileListener', array(
				$container->expand($config['publicDir']),
				$container->expand($config['protectedDir']),
				$container->expand($container->parameters['wwwDir'])
			));



		$container->addDefinition($this->prefix('fileBrowserControlFactory'))
			->setImplement('Venne\Files\FileBrowser\IFileBrowserControlFactory')
			->setArguments(array(
				new Statement('@doctrine.dao', array('Venne\Files\FileEntity')),
				new Statement('@doctrine.dao', array('Venne\Files\DirEntity'))
			))
			->setInject(TRUE);

		$container->addDefinition($this->prefix('defaultPresenter'))
			->setClass('Venne\Files\AdminModule\DefaultPresenter')
			->setArguments(array(
				new Statement('@doctrine.dao', array('Venne\Files\FileEntity')),
				new Statement('@doctrine.dao', array('Venne\Files\DirEntity'))
			))
			->addTag(SystemExtension::TAG_ADMINISTRATION, array(
				'link' => 'Files:Admin:Default:',
				'category' => 'Content',
				'name' => 'Manage files',
				'description' => 'Manage files and directories',
				'priority' => 110,
			));

		$container->addDefinition($this->prefix('filePresenter'))
			->setClass('Venne\Files\AdminModule\FilePresenter')
			->setArguments(array(
				new Statement('@doctrine.dao', array('Venne\Files\FileEntity'))
			));

		$router = $container->getDefinition('router');
		$router->addSetup('$service[] = new Nette\Application\Routers\Route(?, ?);', array(
			'public/media/_cache/<size>/<format>/<type>/<url .+>',
			array(
				'presenter' => 'Files:Admin:File',
				'action' => 'image',
				'url' => array(
					Route::VALUE => '',
					Route::FILTER_IN => NULL,
					Route::FILTER_OUT => NULL,
				)
			)
		));
		$router->addSetup('$service[] = new Nette\Application\Routers\Route(?, ?);', array(
			'public/media/<url .+>',
			array(
				'presenter' => 'Files:Admin:File',
				'action' => 'default',
				'url' => array(
					Route::VALUE => '',
					Route::FILTER_IN => NULL,
					Route::FILTER_OUT => NULL,
				)
			)
		));

		$container->addDefinition($this->prefix('browserControlFactory'))
			->setImplement('Venne\Files\SideComponents\IBrowserControlFactory')
			->setArguments(array(NULL, NULL))
			->setInject(TRUE);

		$container->addDefinition($this->prefix('filesControlFactory'))
			->setImplement('Venne\Files\SideComponents\IFilesControlFactory')
			->setArguments(array(
				new Statement('@doctrine.dao', array('Venne\Files\DirEntity')),
				new Statement('@doctrine.dao', array('Venne\Files\FileEntity')),
			))
			->setInject(TRUE)
			->addTag(SystemExtension::TAG_SIDE_COMPONENT, array(
				'name' => 'Files',
				'description' => 'Files',
				'args' => array(
					'icon' => 'fa fa-folder-open',
				),
			));
	}


	/**
	 * @return array
	 */
	public function getEntityMappings()
	{
		return array(
			'Venne\Files' => dirname(__DIR__) . '/*Entity.php',
		);
	}


	/**
	 * @return array
	 */
	public function getPresenterMapping()
	{
		return array(
			'Files' => 'Venne\Files\*Module\*Presenter',
		);
	}


	/**
	 * @return array
	 */
	public function getTranslationResources()
	{
		return array(
			__DIR__ . '/../../../Resources/lang',
		);
	}


	/**
	 * @return array
	 */
	public function getJsFiles()
	{
		return array(
			'@venne.files/vendor/blueimp-file-upload/jquery.iframe-transport.js',
			'@venne.files/vendor/blueimp-file-upload/jquery.fileupload.js',
		);
	}

}
