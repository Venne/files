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
use Nette\Security\User;
use Venne\Files\AjaxFileUploaderControl;
use Venne\Files\DirEntity;
use Venne\Files\DirFormFactory;
use Venne\Files\FileEditFormFactory;
use Venne\Files\FileEntity;
use Venne\Files\FileFormFactory;
use Venne\Files\IAjaxFileUploaderControlFactory;
use Venne\System\Components\AdminGrid\IAdminGridFactory;
use Venne\System\UI\Control;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FileBrowserControl extends Control
{

	/** @persistent */
	public $key;

	/** @var bool */
	private $browserMode = FALSE;

	/** @var EntityDao */
	private $dirDao;

	/** @var EntityDao */
	private $fileDao;

	/** @var DirFormFactory */
	private $dirFormFactory;

	/** @var FileFormFactory */
	private $fileFormFactory;

	/** @var FileEditFormFactory */
	private $fileEditFormFactory;

	/** @var IAjaxFileUploaderControlFactory */
	private $ajaxFileUploaderFactory;

	/** @var DirEntity|NULL */
	private $root;

	/** @var IAdminGridFactory */
	private $adminGridFactory;

	/** @var User */
	private $user;


	public function __construct(
		EntityDao $fileDao,
		EntityDao $dirDao,
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
		$this->fileFormFactory = $fileForm;
		$this->fileEditFormFactory = $fileEditFormFactory;
		$this->dirFormFactory = $dirForm;
		$this->ajaxFileUploaderFactory = $ajaxFileUploaderFactory;
		$this->adminGridFactory = $adminGridFactory;
		$this->user = $user;
	}


	/**
	 * @param DirEntity|NULL $root
	 */
	public function setRoot(DirEntity $root = NULL)
	{
		$this->root = $root;
	}


	/**
	 * @return DirEntity|NULL
	 */
	public function getRoot()
	{
		return $this->root;
	}


	/**
	 * @param boolean $browserMode
	 */
	public function setBrowserMode($browserMode)
	{
		$this->browserMode = $browserMode;
	}


	/**
	 * @return boolean
	 */
	public function getBrowserMode()
	{
		return $this->browserMode;
	}


	protected function attached($presenter)
	{
		parent::attached($presenter);

		if (substr($this->key, 1, 1) == ':') {
			$this->key = substr($this->key, 2);
		}

		if ($this->root && !$this->key) {
			$this->key = $this->root->getId();
		}

		if (!$this->checkCurrentDir()) {
			throw new BadRequestException;
		}

		if ($this->presenter->getParameter('do') === NULL && $this->presenter->isAjax()) {
			$this->redrawControl('content');
		}
	}


	/**
	 * @return bool
	 */
	public function checkCurrentDir()
	{
		if ($this->root) {
			if ($this->key) {
				$entity = $this->getCurrentDir();
				$t = FALSE;
				while ($entity) {
					if ($entity->id === $this->root->id) {
						$t = TRUE;
						break;
					}
					$entity = $entity->parent;
				}

				if (!$t) {
					return FALSE;
				}
			} else {
				return FALSE;
			}
		}

		return TRUE;
	}


	public function handleChangeDir($id)
	{
		$this->redirect('this', array('key' => $id));
	}


	protected function createComponentAjaxFileUploader()
	{
		$control = $this->ajaxFileUploaderFactory->create();
		$control->onFileUpload[] = $this->handleFileUpload;
		$control->onAfterFileUpload[] = $this->handleFileUploadUnlink;
		$control->onSuccess[] = function () {
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
			$this->redrawControl('content');
		};
		return $control;
	}


	public function handleFileUpload(AjaxFileUploaderControl $control, $fileName)
	{
		/** @var FileEntity $fileEntity */
		$fileEntity = new FileEntity;
		$fileEntity->setFile(new \SplFileInfo($control->getAjaxDir() . '/' . $fileName));
		$fileEntity->setParent($this->getCurrentDir());
		$this->fileDao->save($fileEntity);
	}


	public function handleFileUploadUnlink(AjaxFileUploaderControl $control, $fileName)
	{
		@unlink($control->getAjaxDir() . '/' . $fileName);
		@unlink($control->getAjaxDir() . '/thumbnail/' . $fileName);
	}


	protected function createComponentTable()
	{
		$admin = $this->createTable();
		$admin->onAttached[] = function ($admin) {
			$admin->getTable()->setTemplateFile(__DIR__ . '/Grido.latte');
		};
		$admin->setDao($this->dirDao);
		$table = $admin->getTable();

		$qb = $this->dirDao->createQueryBuilder('a')
			->andWhere('a.invisible = :invisible')->setParameter('invisible', FALSE);

		if ($this->key === NULL) {
			$qb->andWhere('a.parent IS NULL');
		} else {
			$qb->andWhere('a.parent = :par')->setParameter('par', $this->key);
		}

		$table->setModel(new Doctrine($qb));
		$table->setDefaultSort(array('name' => 'ASC'));

		$action = $table->addActionEvent('open', 'Open');
		$action->getElementPrototype()->class[] = 'ajax';
		$action->onClick[] = function ($id) {
			$this->redirect('this', array('key' => $id));
		};

		$table->addActionEvent('edit', 'Edit')
			->getElementPrototype()->class[] = 'ajax';

		$table->addActionEvent('delete', 'Delete')
			->getElementPrototype()->class[] = 'ajax';

		$form = $admin->createForm($this->fileFormFactory, 'File', function () {
			$entity = new FileEntity;
			$entity->setParent($this->getCurrentDir());
			return $entity;
		});
		$dirForm = $admin->createForm($this->dirFormFactory, 'Directory', function () {
			$entity = new DirEntity;
			$entity->setParent($this->getCurrentDir());
			return $entity;
		});

		$admin->connectFormWithAction($dirForm, $table->getAction('edit'));
		$admin->connectActionAsDelete($table->getAction('delete'));

		// Toolbar
		$toolbar = $admin->getNavbar();

		$toolbar->addSection('newDir', 'Create directory', 'file');
		$admin->connectFormWithNavbar($dirForm, $toolbar->getSection('newDir'));

		$toolbar->addSection('new', 'Create file', 'file');
		$admin->connectFormWithNavbar($form, $toolbar->getSection('new'));

		if ($this->key) {
			$toolbar->addSection('up', 'Up')
				->setIcon('arrow-up')
				->onClick[] = function () {
				$dir = $this->getCurrentDir();
				$this->redirect('this', array('key' => $dir->parent ? $dir->parent->id : NULL));
			};
		}

		return $admin;
	}


	protected function createComponentFileTable()
	{
		$admin = $this->createTable();
		$admin->onAttached[] = function ($admin) {
			$admin->getTable()->setTemplateFile(__DIR__ . '/Grido.latte');
		};
		$table = $admin->getTable();

		$qb = $this->fileDao->createQueryBuilder('a')
			->andWhere('a.invisible = :invisible')->setParameter('invisible', FALSE);

		if ($this->key === NULL) {
			$qb->andWhere('a.parent IS NULL');
		} else {
			$qb->andWhere('a.parent = :par')->setParameter('par', $this->key);
		}

		$table->setModel(new Doctrine($qb));
		$table->setDefaultSort(array('name' => 'ASC'));

		$table->addActionEvent('edit', 'Edit')
			->getElementPrototype()->class[] = 'ajax';

		$table->addActionEvent('delete', 'Delete')
			->getElementPrototype()->class[] = 'ajax';

		$form = $admin->createForm($this->fileEditFormFactory, 'File');
		$admin->connectFormWithAction($form, $table->getAction('edit'));
		$admin->connectActionAsDelete($table->getAction('delete'));

		return $admin;
	}


	protected function createTable()
	{
		$admin = $this->adminGridFactory->create($this->fileDao);
		$table = $admin->getTable();

		$table->addColumnText('name', 'Name');

		$table->setDefaultPerPage(99999999999);
		return $admin;
	}


	/**
	 * @return null|DirEntity
	 */
	public function getCurrentDir()
	{
		return $this->key ? $this->dirDao->find($this->key) : NULL;
	}

}
