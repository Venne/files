<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\AdminModule;

use Doctrine\ORM\EntityManager;
use Venne\Files\Dir;
use Venne\Files\FileBrowser\IFileBrowserControlFactory;
use Venne\Files\File;
use Venne\Files\SideComponents\FilesControl;
use Venne\System\AdminPresenterTrait;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class DefaultPresenter extends \Nette\Application\UI\Presenter
{

	use AdminPresenterTrait;

	/** @var \Venne\Files\FileBrowser\IFileBrowserControlFactory */
	private $fileBrowserControlFactory;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $fileRepository;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $dirRepository;

	public function __construct(
		EntityManager $entityManager,
		IFileBrowserControlFactory $fileBrowserControlFactory
	) {
		$this->fileBrowserControlFactory = $fileBrowserControlFactory;
		$this->dirRepository = $entityManager->getRepository(Dir::class);
		$this->fileRepository = $entityManager->getRepository(File::class);
	}

	public function handleChangeDir()
	{
		$this->redirect('this');
		$this->redrawControl('content');
	}

	/**
	 * @return \Venne\Files\FileBrowser\FileBrowserControl
	 */
	protected function createComponentFileBrowser()
	{
		$control = $this->fileBrowserControlFactory->create();

		$sideComponent = $this->getSideComponents()->getSideComponent();
		if ($sideComponent instanceof FilesControl) {
			$control->setSideComponent($sideComponent);
		}

		return $control;
	}

	/**
	 * @secured(privilege="show")
	 */
	public function actionDefault()
	{
	}

	/**
	 * @secured
	 */
	public function actionCreate()
	{
	}

	/**
	 * @secured
	 */
	public function actionEdit()
	{
	}

	/**
	 * @secured
	 */
	public function actionRemove()
	{
	}

}
