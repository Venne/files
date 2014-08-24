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

use Kdyby\Doctrine\EntityDao;
use Venne\Files\FileBrowser\IFileBrowserControlFactory;
use Venne\System\AdminPresenterTrait;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 *
 * @secured
 */
class DefaultPresenter extends \Nette\Application\UI\Presenter
{

	use AdminPresenterTrait;

	/** @var \Venne\Files\FileBrowser\IFileBrowserControlFactory */
	private $fileBrowserControlFactory;

	/** @var \Kdyby\Doctrine\EntityDao */
	private $fileDao;

	/** @var \Kdyby\Doctrine\EntityDao */
	private $dirDao;

	public function __construct(
		EntityDao $fileDao,
		EntityDao $dirDao,
		IFileBrowserControlFactory $fileBrowserControlFactory
	)
	{
		$this->fileBrowserControlFactory = $fileBrowserControlFactory;
		$this->dirDao = $fileDao;
		$this->fileDao = $dirDao;
	}

	/**
	 * @return \Venne\Files\FileBrowser\FileBrowserControl
	 */
	public function createComponentFileBrowser()
	{
		$control = $this->fileBrowserControlFactory->create();

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

	/**
	 * @param string $from
	 * @param string $to
	 * @param string $dropmode
	 *
	 * @secured(privilege="edit")
	 */
	public function handleSetParent($from, $to, $dropmode)
	{
		$dirDao = $this->dirDao;
		$fileDao = $this->fileDao;

		$fromType = substr($from, 0, 1);
		$from = substr($from, 2);

		$toType = substr($to, 0, 1);
		$to = substr($to, 2);

		$entity = $fromType == 'd' ? $dirDao->find($from) : $fileDao->find($from);
		$target = $toType == 'd' ? $dirDao->find($to) : $fileDao->find($to);

		if ($dropmode == 'before' || $dropmode == 'after') {
			$entity->setParent(
				$target->parent ?: null,
				true,
				$dropmode == 'after' ? $target : $target->previous
			);
		} else {
			$entity->setParent($target);
		}

		if ($fromType == 'd') {
			$dirDao->save($entity);
		} else {
			$fileDao->save($entity);
		}

		$this->flashMessage($this->translator->translate('File has been moved'), 'success');

		if (!$this->isAjax()) {
			$this->redirect('this');
		}
		$this['panel']->redrawControl('content');
	}

	/**
	 * @param string $key
	 *
	 * @secured(privilege="remove")
	 */
	public function handleDelete($key)
	{
		$dao = substr($key, 0, 1) == 'd' ? $this->dirDao : $this->fileDao;
		$dao->delete($dao->find(substr($key, 2)));

		if (substr($key, 0, 1) == 'd') {
			$this->flashMessage($this->translator->translate('Directory has been deleted'), 'success');
		} else {
			$this->flashMessage($this->translator->translate('File has been deleted'), 'success');
		}

		if (!$this->isAjax()) {
			$this->redirect('this');
		}
		$this->payload->url = $this->link('this');
		$this->redrawControl('content');
		$this['panel']->redrawControl('content');
	}

}
