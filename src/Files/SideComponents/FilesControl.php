<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\SideComponents;

use Doctrine\ORM\EntityManager;
use Nette\Http\Session;
use Venne\Files\Dir;
use Venne\Files\File;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FilesControl extends \Venne\System\UI\Control
{

	/** @var \Nette\Http\SessionSection */
	private $session;

	/** @var \Doctrine\ORM\EntityManager */
	private $entityManager;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $dirRepository;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $fileRepository;

	/** @var \Venne\Files\SideComponents\IBrowserControlFactory */
	private $browserFactory;

	public function __construct(EntityManager $entityManager, Session $session, IBrowserControlFactory $browserFactory)
	{
		parent::__construct();

		$this->entityManager = $entityManager;
		$this->fileRepository = $entityManager->getRepository(File::class);
		$this->dirRepository = $entityManager->getRepository(Dir::class);
		$this->session = $session->getSection('Venne.Content.filesSide');
		$this->browserFactory = $browserFactory;
	}

	public function render()
	{
		$this->template->render();
	}

	public function redrawContent()
	{
		$this->redrawControl('content');
	}

	/**
	 * @param int $id
	 * @param bool $state
	 */
	public function setState($id, $state)
	{
		if (!isset($this->session->state)) {
			$this->session->state = array();
		}

		$this->session->state[$id] = $state;
	}

	/**
	 * @param int $id
	 * @return bool
	 */
	public function getState($id)
	{
		return isset($this->session->state[$id]) ? $this->session->state[$id] : false;
	}

	/**
	 * @return \Venne\Files\SideComponents\BrowserControl
	 */
	protected function createComponentBrowser()
	{
		$browser = $this->browserFactory->create();
		$browser->setLoadCallback($this->getFiles);
		$browser->setDropCallback($this->setFileParent);
		$browser->onClick[] = function ($key) {
			if (substr($key, 0, 2) === 'd:') {
				$this->getPresenter()->forward(':Admin:Files:Default:', array(
					'fileBrowser-key' => substr($key, 2),
					'do' => 'changeDir',
				));
			}
		};
		$browser->onExpand[] = $this->fileExpand;

		return $browser;
	}

	/**
	 * @param string $key
	 * @param string $open
	 */
	public function fileExpand($key, $open)
	{
		$key = $key ? substr($key, 2) : null;
		$this->setState((int) $key, $open);
	}

	/**
	 * @param string $parent
	 * @return mixed[]
	 */
	public function getFiles($parent = null)
	{
		$parent = $parent ? substr($parent, 2) : null;

		$this->setState((int) $parent, true);

		$data = array();

		$dql = $this->dirRepository->createQueryBuilder('a')
			->orderBy('a.name', 'ASC');
		if ($parent) {
			$dql = $dql->andWhere('a.parent = ?1')->setParameter(1, $parent);
		} else {
			$dql = $dql->andWhere('a.parent IS NULL');
		}
		$dql = $dql->andWhere('a.invisible = :invisible')->setParameter('invisible', false);

		foreach ($dql->getQuery()->getResult() as $page) {
			$item = array('title' => $page->name, 'key' => 'd:' . $page->id);

			$item['folder'] = true;

			if (count($page->children) > 0 || count($page->files) > 0) {
				$item['lazy'] = true;
			}

			if ($this->getState($page->id)) {
				$item['expanded'] = true;
				$item['children'] = $this->getFiles('d:' . $page->id);
			}

			$data[] = $item;
		}

		$dql = $this->fileRepository->createQueryBuilder('a')
			->orderBy('a.name', 'ASC');
		if ($parent) {
			$dql = $dql->andWhere('a.parent = ?1')->setParameter(1, $parent);
		} else {
			$dql = $dql->andWhere('a.parent IS NULL');
		}
		$dql = $dql->andWhere('a.invisible = :invisible')->setParameter('invisible', false);

		foreach ($dql->getQuery()->getResult() as $page) {
			$item = array('title' => $page->name, 'key' => 'f:' . $page->id);
			$data[] = $item;
		}

		return $data;
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param string $dropMode
	 */
	public function setFileParent($from, $to, $dropMode)
	{
		$fromType = substr($from, 0, 1);
		$from = substr($from, 2);

		$toType = substr($to, 0, 1);
		$to = substr($to, 2);

		$entity = $fromType == 'd' ? $this->dirRepository->find($from) : $this->fileRepository->find($from);
		$target = $toType == 'd' ? $this->dirRepository->find($to) : $this->fileRepository->find($to);

		if ($dropMode === 'before' || $dropMode === 'after') {
			$entity->setParent($target->parent);
		} else {
			$entity->setParent($target);
		}

		$this->entityManager->flush($entity);
	}

}
