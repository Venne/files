<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\FileBrowser;

use Grido\DataSources\Doctrine;
use Kdyby\Doctrine\EntityDao;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Forms\Form;
use Nette\Security\User;
use Venne\Files\AjaxFileUploaderControl;
use Venne\Files\DirEntity;
use Venne\Files\DirFormFactory;
use Venne\Files\FileEditFormFactory;
use Venne\Files\FileEntity;
use Venne\Files\FileFormFactory;
use Venne\Files\IAjaxFileUploaderControlFactory;
use Venne\System\Components\AdminGrid\IAdminGridFactory;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FileBrowserControl extends \Venne\System\UI\Control
{

	/** @var \Venne\Files\DirEntity|null */
	private $dir;

	/** @var bool */
	private $browserMode = false;

	/** @var \Kdyby\Doctrine\EntityDao */
	private $dirDao;

	/** @var \Kdyby\Doctrine\EntityDao */
	private $fileDao;

	/** @var \Kdyby\Doctrine\EntityDao */
	private $userDao;

	/** @var \Venne\Files\DirFormFactory */
	private $dirFormFactory;

	/** @var \Venne\Files\FileFormFactory */
	private $fileFormFactory;

	/** @var \Venne\Files\FileEditFormFactory */
	private $fileEditFormFactory;

	/** @var \Venne\Files\IAjaxFileUploaderControlFactory */
	private $ajaxFileUploaderFactory;

	/** @var \Venne\Files\DirEntity|null */
	private $root;

	/** @var \Venne\System\Components\AdminGrid\IAdminGridFactory */
	private $adminGridFactory;

	/** @var \Nette\Security\User */
	private $user;

	public function __construct(
		EntityDao $fileDao,
		EntityDao $dirDao,
		EntityDao $userDao,
		FileFormFactory $fileForm,
		FileEditFormFactory $fileEditFormFactory,
		DirFormFactory $dirForm,
		IAjaxFileUploaderControlFactory $ajaxFileUploaderFactory,
		IAdminGridFactory $adminGridFactory,
		User $user
	)
	{
		$this->fileDao = $fileDao;
		$this->dirDao = $dirDao;
		$this->userDao = $userDao;
		$this->fileFormFactory = $fileForm;
		$this->fileEditFormFactory = $fileEditFormFactory;
		$this->dirFormFactory = $dirForm;
		$this->ajaxFileUploaderFactory = $ajaxFileUploaderFactory;
		$this->adminGridFactory = $adminGridFactory;
		$this->user = $user;
	}

	public function render()
	{
		$this->template->dir = $this->dir;
		$this->template->root = $this->root;
		parent::render();
	}

	/**
	 * @param \Venne\Files\DirEntity|null $root
	 */
	public function setRoot(DirEntity $root = null)
	{
		$this->root = $root;
	}

	/**
	 * @return \Venne\Files\DirEntity|null
	 */
	public function getRoot()
	{
		return $this->root;
	}

	/**
	 * @param bool $browserMode
	 */
	public function setBrowserMode($browserMode)
	{
		$this->browserMode = (bool) $browserMode;
	}

	/**
	 * @return bool
	 */
	public function getBrowserMode()
	{
		return $this->browserMode;
	}

	/**
	 * @param \Nette\ComponentModel\IComponent $presenter
	 */
	protected function attached($presenter)
	{
		parent::attached($presenter);

		if (!$this->checkCurrentDir()) {
			throw new BadRequestException;
		}
	}

	/**
	 * @return bool
	 */
	public function checkCurrentDir()
	{
		if ($this->root) {
			if ($this->dir) {
				$entity = $this->dir;
				$t = false;
				while ($entity) {
					if ($entity->id === $this->root->id) {
						$t = true;
						break;
					}
					$entity = $entity->parent;
				}

				if (!$t) {
					return false;
				}
			} else {
				return false;
			}
		}

		return true;
	}

	public function handleChangeDir()
	{
		$this->redirect('this');
		$this->redrawControl('content');
	}

	protected function createComponentAjaxFileUploader()
	{
		$control = $this->ajaxFileUploaderFactory->create();
		$control->onFileUpload[] = $this->handleFileUpload;
		$control->onAfterFileUpload[] = $this->handleFileUploadUnlink;
		$control->onSuccess[] = function () {
			$this->redirect('this');
			$this->redrawControl('content');
		};
		$control->onError[] = function (AjaxFileUploaderControl $control) {
			foreach ($control->getErrors() as $e) {
				if ($e['class'] === 'Doctrine\DBAL\DBALException' && strpos($e['message'], 'Duplicate entry') !== false) {
					$this->flashMessage($this->translator->translate('Duplicate entry'), 'warning');
				} else {
					$this->flashMessage($e['message']);
				}
			}

			$this->redirect('this');
			$this->redrawControl('content');
		};

		return $control;
	}

	/**
	 * @param \Venne\Files\AjaxFileUploaderControl $control
	 * @param string $fileName
	 */
	public function handleFileUpload(AjaxFileUploaderControl $control, $fileName)
	{
		$fileEntity = new FileEntity;
		$fileEntity->setParent($this->dir);
		$fileEntity->setFile(new \SplFileInfo($control->getAjaxDir() . '/' . $fileName));
		$fileEntity->setAuthor($this->userDao->find($this->user->getIdentity()->getId()));
		$this->fileDao->save($fileEntity);
	}

	/**
	 * @param \Venne\Files\AjaxFileUploaderControl $control
	 * @param string $fileName
	 */
	public function handleFileUploadUnlink(AjaxFileUploaderControl $control, $fileName)
	{
		@unlink($control->getAjaxDir() . '/' . $fileName);
		@unlink($control->getAjaxDir() . '/thumbnail/' . $fileName);
	}

	/**
	 * @return \Venne\System\Components\AdminGrid\AdminGrid
	 */
	protected function createComponentTable()
	{
		$admin = $this->createTable();
		$admin->setDao($this->dirDao);
		$table = $admin->getTable();

		$admin->onRender[] = function () use ($table) {
			$qb = $this->dirDao->createQueryBuilder('a')
				->andWhere('a.invisible = :invisible')->setParameter('invisible', false);

			if ($this->dir === null) {
				$qb->andWhere('a.parent IS NULL');
			} else {
				$qb->andWhere('a.parent = :par')->setParameter('par', $this->dir->getId());
			}

			$table->setModel(new Doctrine($qb));
		};

		$table->setDefaultSort(array('name' => 'ASC'));

		$action = $table->addActionEvent('open', 'Open');
		$action->getElementPrototype()->class[] = 'ajax';
		$action->onClick[] = function ($id) use ($table) {
			$this->redirect('this', array(
				'key' => $id
			));

			$this->redrawControl('content');
		};

		$action = $table->addActionEvent('edit', 'Edit');
		$action->getElementPrototype()->class[] = 'ajax';
		$action = $table->addActionEvent('delete', 'Delete');
		$action->getElementPrototype()->class[] = 'ajax';

		$form = $admin->createForm($this->fileFormFactory, 'File', function () {
			$entity = new FileEntity;
			$entity->setParent($this->dir);

			return $entity;
		});
		$dirForm = $admin->createForm($this->dirFormFactory, 'Directory', function () {
			$entity = new DirEntity;
			$entity->setParent($this->dir);

			return $entity;
		});

		$admin->connectFormWithAction($dirForm, $table->getAction('edit'));
		$action = $table->getAction('delete');
		$admin->connectActionAsDelete($action);
		$action->onClick[] = function () {
			$this->redrawControl('content');
		};

		// Toolbar
		$toolbar = $admin->getNavbar();

		$toolbar->addSection('newDir', 'Create directory', 'file');
		$admin->connectFormWithNavbar($dirForm, $toolbar->getSection('newDir'));

		$toolbar->addSection('new', 'Create file', 'file');
		$admin->connectFormWithNavbar($form, $toolbar->getSection('new'));

		$admin->onClose[] = function () {
			$this->redrawControl('content');
		};

		$toolbar->addSection('up', 'Up')
			->setIcon('arrow-up')
			->onClick[] = function () {
			$dir = $this->dir;

			$this->redirect('this', array(
				'key' => $dir->parent ? $dir->parent->id : null,
			));

			$this->dir = $dir->parent ?: null;
			$this->redrawControl('content');
		};

		return $admin;
	}

	/**
	 * @return \Venne\System\Components\AdminGrid\AdminGrid
	 */
	protected function createComponentFileTable()
	{
		$admin = $this->createTable();
		$table = $admin->getTable();

		$qb = $this->fileDao->createQueryBuilder('a')
			->andWhere('a.invisible = :invisible')->setParameter('invisible', false);

		if ($this->dir === null) {
			$qb->andWhere('a.parent IS NULL');
		} else {
			$qb->andWhere('a.parent = :par')->setParameter('par', $this->dir->getId());
		}

		$table->setModel(new Doctrine($qb));
		$table->setDefaultSort(array('name' => 'ASC'));

		$action = $table->addActionEvent('open', 'Open');
		$action->onClick[] = function ($id) use ($table) {
			$this->getPresenter()->redirectUrl($this->fileDao->find($id)->getFileUrl());
		};

		$action = $table->addActionEvent('download', 'Download');
		$action->onClick[] = function ($id) use ($table) {
			$file = $this->fileDao->find($id);
			$this->getPresenter()->sendResponse(new FileResponse($file->getFilePath()));
		};

		$table->addActionEvent('edit', 'Edit')
			->getElementPrototype()->class[] = 'ajax';

		$table->addActionEvent('delete', 'Delete')
			->getElementPrototype()->class[] = 'ajax';

		$form = $admin->createForm($this->fileEditFormFactory, 'File');
		$admin->connectFormWithAction($form, $table->getAction('edit'));
		$action = $table->getAction('delete');
		$admin->connectActionAsDelete($action);
		$action->onClick[] = function () {
			$this->redrawControl('content');
		};

		return $admin;
	}

	/**
	 * @return \Venne\System\Components\AdminGrid\AdminGrid
	 */
	protected function createTable()
	{
		$admin = $this->adminGridFactory->create($this->fileDao);
		$admin->onRender[] = function ($admin) {
			$admin->getTable()->setTemplateFile(__DIR__ . '/Grido.latte');
		};

		$table = $admin->getTable();

		$table->addColumnText('name', 'Name');

		$table->setDefaultPerPage(99999999999);

		return $admin;
	}

	public function loadState(array $params)
	{
		if (isset($params['key'])) {
			if (substr($params['key'], 1, 1) == ':') {
				$params['key'] = substr($params['key'], 2);
			}

			$this->dir = $this->dirDao->find($params['key']);
		}  elseif ($this->root) {
			$this->dir = $this->root;
		}

		parent::loadState($params);
	}

	public function saveState(array & $params, $reflection = null)
	{
		parent::saveState($params, $reflection);

		if ($this->dir !== null) {
			$params['key'] = $this->dir->id;
		}
	}

}
