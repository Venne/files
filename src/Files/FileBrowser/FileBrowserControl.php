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

use Doctrine\ORM\EntityManager;
use Grido\DataSources\Doctrine;
use Nette\Application\BadRequestException;
use Nette\Application\Responses\FileResponse;
use Nette\Security\User as NetteUser;
use Venne\Files\AjaxFileUploaderControl;
use Venne\Files\Dir;
use Venne\Files\DirFormFactory;
use Venne\Files\DirFormService;
use Venne\Files\FileEditFormFactory;
use Venne\Files\File;
use Venne\Files\FileFormFactory;
use Venne\Files\FileFormService;
use Venne\Files\IAjaxFileUploaderControlFactory;
use Venne\Security\User;
use Venne\System\Components\AdminGrid\IAdminGridFactory;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FileBrowserControl extends \Venne\System\UI\Control
{

	/** @var \Venne\Files\Dir|null */
	private $dir;

	/** @var bool */
	private $browserMode = false;

	/** @var \Doctrine\ORM\EntityManager */
	private $entityManager;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $dirRepository;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $fileRepository;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $userRepository;

	/** @var \Venne\Files\IAjaxFileUploaderControlFactory */
	private $ajaxFileUploaderFactory;

	/** @var \Venne\Files\Dir|null */
	private $root;

	/** @var \Venne\System\Components\AdminGrid\IAdminGridFactory */
	private $adminGridFactory;

	/** @var \Nette\Security\User */
	private $netteUser;

	/** @var \Venne\Files\FileFormService */
	private $fileFormService;

	/** @var \Venne\Files\DirFormService */
	private $dirFormService;

	public function __construct(
		EntityManager $entityManager,
		FileFormService $fileFormService,
		DirFormService $dirFormService,
		IAjaxFileUploaderControlFactory $ajaxFileUploaderFactory,
		IAdminGridFactory $adminGridFactory,
		NetteUser $netteUser
	)
	{
		$this->entityManager = $entityManager;
		$this->fileRepository = $entityManager->getRepository(File::class);
		$this->dirRepository = $entityManager->getRepository(Dir::class);
		$this->userRepository = $entityManager->getRepository(User::class);
		$this->fileFormService = $fileFormService;
		$this->dirFormService = $dirFormService;
		$this->ajaxFileUploaderFactory = $ajaxFileUploaderFactory;
		$this->adminGridFactory = $adminGridFactory;
		$this->netteUser = $netteUser;
	}

	public function render()
	{
		$this->template->dir = $this->dir;
		$this->template->root = $this->root;
		parent::render();
	}

	/**
	 * @param \Venne\Files\Dir|null $root
	 */
	public function setRoot(Dir $root = null)
	{
		$this->root = $root;
	}

	/**
	 * @return \Venne\Files\Dir|null
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
		$file = new File;
		$file->setParent($this->dir);
		$file->setFile(new \SplFileInfo($control->getAjaxDir() . '/' . $fileName));
		$file->setAuthor($this->userRepository->find($this->netteUser->getIdentity()->getId()));
		$this->entityManager->persist($file);
		$this->entityManager->flush();
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
		$admin->setRepository($this->dirRepository);
		$table = $admin->getTable();

		$admin->onRender[] = function () use ($table) {
			$qb = $this->dirRepository->createQueryBuilder('a')
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

		$form = $admin->addForm('file', 'File', function (File $file = null) {
			return $this->fileFormService->getFormFactory(
				$file !== null ? $file->getId() : null,
				$this->getParameter('key')
			);
		});
		$dirForm = $admin->addForm('directory', 'Directory', function (Dir $dir = null) {
			return $this->dirFormService->getFormFactory(
				$dir !== null ? $dir->getId() : null,
				$this->getParameter('key')
			);
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

		$section = $toolbar->addSection('up', 'Up');
		$section->setIcon('arrow-up');
		$section->onClick[] = function () {
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

		$qb = $this->fileRepository->createQueryBuilder('a')
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
			$this->getPresenter()->redirectUrl($this->fileRepository->find($id)->getFileUrl());
		};

		$action = $table->addActionEvent('download', 'Download');
		$action->onClick[] = function ($id) use ($table) {
			$file = $this->fileRepository->find($id);
			$this->getPresenter()->sendResponse(new FileResponse($file->getFilePath()));
		};

		$table->addActionEvent('edit', 'Edit')
			->getElementPrototype()->class[] = 'ajax';

		$table->addActionEvent('delete', 'Delete')
			->getElementPrototype()->class[] = 'ajax';

		$form = $admin->addForm('file', 'File', function (File $file = null) {
			return $this->fileFormService->getFormFactory(
				$file !== null ? $file->getId() : null,
				$this->getParameter('key'),
				FileFormService::TYPE_EDIT
			);
		});
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
		$admin = $this->adminGridFactory->create($this->fileRepository);
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

			$this->dir = $this->dirRepository->find($params['key']);
		} elseif ($this->root) {
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
